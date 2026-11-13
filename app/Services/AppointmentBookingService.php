<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AppointmentBookingService
{
    public function lockDoctor(int $id): void
    {
        // Serialize all scheduling operations on this doctor, across branches.
        // A write also acquires the lock before availability reads on SQLite.
        DB::table('doctors')->where('id', $id)->update(['updated_at' => DB::raw('updated_at')]);
    }

    public function availability(Doctor $doctor, int $branchId, string $date, ?int $exclude = null): array
    {
        if (! $doctor->is_active || ! $doctor->user?->is_active || $doctor->user->status !== 'ACTIVE'
            || ! $doctor->user->branches()->where('branches.id', $branchId)->where('is_active', true)->exists()) {
            return [];
        }
        $slots = [];
        foreach ($doctor->schedules()->where('branch_id', $branchId)->whereDate('work_date', $date)->where('is_open', true)->orderBy('start_time')->get() as $shift) {
            for ($start = Carbon::parse($date.' '.$shift->start_time); $start->copy()->addMinutes($shift->slot_minutes)->lte(Carbon::parse($date.' '.$shift->end_time)); $start->addMinutes($shift->slot_minutes)) {
                $end = $start->copy()->addMinutes($shift->slot_minutes);
                if ($start->isPast() || DB::table('doctor_time_off')->where('doctor_id', $doctor->id)->where('starts_at', '<', $end)->where('ends_at', '>', $start)->exists()) {
                    continue;
                }
                $count = Appointment::where('doctor_id', $doctor->id)->whereDate('appointment_date', $date)
                    ->whereNotIn('status', ['CANCELLED', 'NO_SHOW'])->when($exclude, fn ($q) => $q->where('id', '!=', $exclude))
                    ->where('start_time', '<', $end->format('H:i:s'))->where('end_time', '>', $start->format('H:i:s'))->count();
                if ($count < $shift->capacity) {
                    $slots[] = ['start_time' => $start->format('H:i'), 'end_time' => $end->format('H:i'), 'remaining' => $shift->capacity - $count, 'schedule_id' => $shift->id];
                }
            }
        }

        return $slots;
    }

    private function slot(Patient $patient, Doctor $doctor, int $branchId, string $date, string $time, ?int $exclude = null): array
    {
        if (! $patient->is_active) {
            throw ValidationException::withMessages(['patient_id' => 'Hồ sơ bệnh nhân đã vô hiệu hóa.']);
        }
        $slot = collect($this->availability($doctor, $branchId, $date, $exclude))->firstWhere('start_time', substr($time, 0, 5));
        if (! $slot) {
            throw ValidationException::withMessages(['start_time' => 'Giờ không khả dụng: sai bước ca, hết chỗ, bác sĩ nghỉ hoặc chưa được phân công chi nhánh.']);
        }
        if (Appointment::where('patient_id', $patient->id)->whereDate('appointment_date', $date)->whereNotIn('status', ['CANCELLED', 'NO_SHOW'])
            ->when($exclude, fn ($q) => $q->where('id', '!=', $exclude))->where('start_time', '<', $slot['end_time'].':00')->where('end_time', '>', $slot['start_time'].':00')->exists()) {
            throw ValidationException::withMessages(['start_time' => 'Bệnh nhân đã có lịch trong khoảng giờ này.']);
        }

        return $slot;
    }

    public function book(Patient $patient, Doctor $doctor, string $date, string $startTime, string $reason, ?User $creator = null, ?int $branchId = null, ?string $key = null): Appointment
    {
        $branchId ??= Branch::where('is_active', true)->value('id');

        return DB::transaction(function () use ($patient, $doctor, $date, $startTime, $reason, $creator, $branchId, $key) {
            $this->lockDoctor($doctor->id);
            DB::table('patients')->where('id', $patient->id)->update(['updated_at' => DB::raw('updated_at')]);
            $doctor->refresh();
            $patient->refresh();
            if ($key && ($existing = Appointment::where('idempotency_key', $key)->first())) {
                abort_unless($existing->patient_id === $patient->id && $existing->doctor_id === $doctor->id && $existing->branch_id === $branchId
                    && $existing->appointment_date->format('Y-m-d') === $date && substr($existing->start_time, 0, 5) === substr($startTime, 0, 5), 409, 'Khóa yêu cầu đã dùng cho lịch khác.');

                return $existing;
            }
            $slot = $this->slot($patient, $doctor, $branchId, $date, $startTime);
            $a = Appointment::create([
                'appointment_code' => 'LH'.strtoupper(bin2hex(random_bytes(8))), 'patient_id' => $patient->id,
                'doctor_id' => $doctor->id, 'branch_id' => $branchId, 'doctor_schedule_id' => $slot['schedule_id'],
                'created_by' => $creator?->id, 'appointment_date' => $date, 'start_time' => $slot['start_time'].':00',
                'end_time' => $slot['end_time'].':00', 'reason' => $reason, 'status' => config('clinic.booking_status'), 'idempotency_key' => $key,
            ]);
            app(NotificationService::class)->appointment($a, 'CREATED');
            AuditLog::record('appointment.created', $a, [], $a->only(['status', 'appointment_date', 'start_time']));

            return $a;
        }, 5);
    }

    public function reschedule(Appointment $appointment, string $date, string $time, string $reason, User $actor): Appointment
    {
        return DB::transaction(function () use ($appointment, $date, $time, $reason, $actor) {
            $this->lockDoctor($appointment->doctor_id);
            $a = Appointment::lockForUpdate()->findOrFail($appointment->id);
            if ($a->doctor_id !== $appointment->doctor_id) {
                throw ValidationException::withMessages(['appointment' => 'Bác sĩ phụ trách đã thay đổi. Vui lòng tải lại lịch trước khi tiếp tục.']);
            }
            DB::table('patients')->where('id', $a->patient_id)->update(['updated_at' => DB::raw('updated_at')]);
            if (! in_array($a->status, ['PENDING', 'CONFIRMED'])) {
                throw ValidationException::withMessages(['appointment' => 'Không thể đổi lịch đăng ký ở trạng thái hiện tại.']);
            }
            if ($actor->hasRole('patient')) {
                $this->checkCutoff($a, 'đổi');
            }
            $slot = $this->slot($a->patient, $a->doctor, $a->branch_id, $date, $time, $a->id);
            $old = $a->only(['appointment_date', 'start_time', 'end_time', 'doctor_id', 'branch_id']);
            $a->update(['appointment_date' => $date, 'start_time' => $slot['start_time'].':00', 'end_time' => $slot['end_time'].':00', 'doctor_schedule_id' => $slot['schedule_id']]);
            DB::table('appointment_history')->insert(['appointment_id' => $a->id, 'old_values' => json_encode($old), 'new_values' => json_encode($a->only(array_keys($old))), 'reason' => $reason, 'changed_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
            app(NotificationService::class)->cancelReminders($a->id);
            app(NotificationService::class)->appointment($a, 'RESCHEDULED');
            AuditLog::record('appointment.rescheduled', $a, $old, $a->only(array_keys($old)));

            return $a;
        }, 5);
    }

    public function cancel(Appointment $appointment, string $reason, User $actor): void
    {
        DB::transaction(function () use ($appointment, $reason, $actor) {
            $this->lockDoctor($appointment->doctor_id);
            $a = Appointment::lockForUpdate()->findOrFail($appointment->id);
            if ($a->doctor_id !== $appointment->doctor_id) {
                throw ValidationException::withMessages(['appointment' => 'Bác sĩ phụ trách đã thay đổi. Vui lòng tải lại lịch trước khi tiếp tục.']);
            }
            if (! in_array($a->status, ['PENDING', 'CONFIRMED'])) {
                throw ValidationException::withMessages(['appointment' => 'Không thể hủy lịch đăng ký ở trạng thái hiện tại.']);
            }
            if ($actor->hasRole('patient')) {
                $this->checkCutoff($a, 'hủy');
            }
            $a->update(['status' => 'CANCELLED', 'cancel_reason' => $reason, 'cancelled_at' => now()]);
            app(NotificationService::class)->cancelReminders($a->id);
            app(NotificationService::class)->appointment($a, 'CANCELLED');
            AuditLog::record('appointment.cancelled', $a, [], ['reason' => $reason]);
        }, 5);
    }

    private function checkCutoff(Appointment $a, string $action): void
    {
        if (! $a->patientCanChange()) {
            $hours = config('clinic.cancellation_hours');
            throw ValidationException::withMessages([
                'appointment' => "Không thể {$action} lịch đăng ký. Bạn chỉ được {$action} lịch trước giờ khám đã đăng ký ít nhất {$hours} giờ.",
            ]);
        }
    }
}
