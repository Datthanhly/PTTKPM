<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ScheduleService
{
    public function create(array $data): void
    {
        ClinicAccess::require('SCHEDULE_MANAGE', (int) $data['branch_id']);
        DB::transaction(function () use ($data) {
            app(AppointmentBookingService::class)->lockDoctor((int) $data['doctor_id']);
            $this->createLocked($data, 'schedule.created');
        }, 5);
    }

    public function registerOwn(array $data): int
    {
        $user = auth()->user();
        $doctor = $user?->doctor;
        abort_unless($user?->hasRole('doctor') && $user->is_active && $user->status === 'ACTIVE' && $doctor?->is_active, 403);
        $periods = match ($data['session']) {
            'both' => array_values(config('clinic.working_hours')),
            'custom' => [[$data['start_time'], $data['end_time']]],
            default => [config('clinic.working_hours.'.$data['session'])],
        };

        return DB::transaction(function () use ($data, $doctor, $periods) {
            app(AppointmentBookingService::class)->lockDoctor($doctor->id);
            $until = Carbon::parse($data['repeat_until'] ?? $data['work_date']);
            $count = 0;
            for ($date = Carbon::parse($data['work_date']); $date->lte($until); $date->addWeek()) {
                foreach ($periods as [$start, $end]) {
                    if (Carbon::parse($date->format('Y-m-d').' '.$start)->isPast()) {
                        throw ValidationException::withMessages(['work_date' => 'Không thể đăng ký ca làm đã bắt đầu. Hãy chọn ngày hoặc giờ khác.']);
                    }
                    $this->createLocked([
                        'doctor_id' => $doctor->id, 'branch_id' => $data['branch_id'], 'work_date' => $date->format('Y-m-d'),
                        'start_time' => $start, 'end_time' => $end, 'slot_minutes' => $data['slot_minutes'],
                        'capacity' => $data['capacity'], 'is_open' => true,
                    ], 'schedule.self_registered');
                    $count++;
                }
            }

            return $count;
        }, 5);
    }

    private function createLocked(array $data, string $action): void
    {
        $doctor = Doctor::findOrFail($data['doctor_id']);
        if (! $doctor->is_active || ! $doctor->user->is_active || $doctor->user->status !== 'ACTIVE'
            || ! $doctor->user->branches()->where('branches.id', $data['branch_id'])->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['branch_id' => 'Bác sĩ chưa được phân công tại chi nhánh đang hoạt động này.']);
        }
        $start = Carbon::parse($data['work_date'].' '.$data['start_time']);
        $end = Carbon::parse($data['work_date'].' '.$data['end_time']);
        if ($end->lte($start) || $start->diffInMinutes($end) < $data['slot_minutes']) {
            throw ValidationException::withMessages(['end_time' => 'Ca làm phải đủ thời lượng ít nhất một lượt khám và kết thúc sau giờ bắt đầu.']);
        }
        if (DoctorSchedule::where('doctor_id', $doctor->id)->whereDate('work_date', $data['work_date'])
            ->where('start_time', '<', $data['end_time'])->where('end_time', '>', $data['start_time'])->exists()) {
            throw ValidationException::withMessages(['work_date' => 'Trùng ca làm của bác sĩ ngày '.$start->format('d/m/Y').' (kể cả ca đã đóng hoặc ở chi nhánh khác). Hãy chọn khoảng giờ khác.']);
        }
        if (DB::table('doctor_time_off')->where('doctor_id', $doctor->id)->where('starts_at', '<', $end)->where('ends_at', '>', $start)->exists()) {
            throw ValidationException::withMessages(['work_date' => 'Ca đăng ký ngày '.$start->format('d/m/Y').' trùng lịch nghỉ của bác sĩ. Hãy chọn ngày hoặc giờ khác.']);
        }
        $schedule = DoctorSchedule::create($data);
        AuditLog::record($action, $schedule, [], $schedule->only(['work_date', 'start_time', 'end_time', 'slot_minutes', 'capacity']));
    }

    public function timeOff(array $data): void
    {
        DB::transaction(function () use ($data) {
            app(AppointmentBookingService::class)->lockDoctor((int) $data['doctor_id']);
            $start = Carbon::parse($data['starts_at']);
            $end = Carbon::parse($data['ends_at']);
            $affected = Appointment::where('doctor_id', $data['doctor_id'])->whereNotIn('status', ['CANCELLED', 'NO_SHOW', 'COMPLETED'])->get()
                ->filter(fn ($a) => Carbon::parse($a->appointment_date->format('Y-m-d').' '.$a->start_time)->lt($end) && Carbon::parse($a->appointment_date->format('Y-m-d').' '.$a->end_time)->gt($start));
            abort_if($affected->contains(fn ($a) => $a->status === 'CHECKED_IN'), 422, 'Có lượt đã tiếp nhận trong khoảng nghỉ.');
            abort_if($affected->isNotEmpty() && empty($data['cancel_affected']), 422, 'Lịch bị ảnh hưởng: '.$affected->pluck('appointment_code')->join(', ').'. Hãy đổi lịch trước hoặc chọn hủy có thông báo.');
            foreach ($affected as $a) {
                app(AppointmentBookingService::class)->cancel($a, 'Bác sĩ nghỉ: '.$data['reason'], auth()->user());
            }
            DB::table('doctor_time_off')->insert(['doctor_id' => $data['doctor_id'], 'starts_at' => $start, 'ends_at' => $end, 'reason' => $data['reason'], 'created_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
            AuditLog::record('doctor.time_off_created', Doctor::findOrFail($data['doctor_id']), [], ['affected_count' => $affected->count()]);
        }, 5);
    }
}
