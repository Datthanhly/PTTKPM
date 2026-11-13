<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\ShiftTransferRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShiftTransferService
{
    public function candidates(DoctorSchedule $schedule)
    {
        $specialty = $schedule->doctor->specialty_id;

        return Doctor::with('user')->whereKeyNot($schedule->doctor_id)->where('is_active', true)
            ->where(fn ($q) => $q->where('specialty_id', $specialty)->orWhereHas('specialties', fn ($s) => $s->where('specialties.id', $specialty)))
            ->whereHas('user', fn ($q) => $q->where('is_active', true)->where('status', 'ACTIVE')->whereHas('role', fn ($r) => $r->where('code', 'doctor'))->whereHas('branches', fn ($b) => $b->where('branches.id', $schedule->branch_id)->where('is_active', true)))
            ->orderBy('doctor_code')->get()->filter(fn ($doctor) => $this->unavailableReason($doctor, $schedule) === null)->values();
    }

    public function unavailableReason(Doctor $doctor, DoctorSchedule $schedule): ?string
    {
        if ($doctor->id === $schedule->doctor_id || ! $doctor->is_active || ! $doctor->user->hasRole('doctor') || ! $doctor->user->is_active || $doctor->user->status !== 'ACTIVE'
            || ! $doctor->user->branches()->where('branches.id', $schedule->branch_id)->where('is_active', true)->exists()) {
            return 'Bác sĩ nhận ca chưa hoạt động hoặc chưa được phân công tại chi nhánh này.';
        }
        $specialty = $schedule->doctor->specialty_id;
        if ($doctor->specialty_id !== $specialty && ! $doctor->specialties()->where('specialties.id', $specialty)->exists()) {
            return 'Bác sĩ nhận ca phải có cùng chuyên khoa với ca đang đăng ký.';
        }
        if ($doctor->schedules()->whereDate('work_date', $schedule->work_date)->where('start_time', '<', $schedule->end_time)->where('end_time', '>', $schedule->start_time)->exists()) {
            return 'Bác sĩ nhận ca đã có ca làm trùng giờ, kể cả ở chi nhánh khác hoặc ca đã đóng.';
        }
        $start = Carbon::parse($schedule->work_date->format('Y-m-d').' '.$schedule->start_time);
        $end = Carbon::parse($schedule->work_date->format('Y-m-d').' '.$schedule->end_time);
        if (DB::table('doctor_time_off')->where('doctor_id', $doctor->id)->where('starts_at', '<', $end)->where('ends_at', '>', $start)->exists()) {
            return 'Bác sĩ nhận ca có lịch nghỉ trong khoảng giờ này.';
        }
        if ($doctor->appointments()->whereDate('appointment_date', $schedule->work_date)->whereNotIn('status', ['CANCELLED', 'NO_SHOW'])
            ->where('start_time', '<', $schedule->end_time)->where('end_time', '>', $schedule->start_time)->exists()
            || DB::table('visits')->where('doctor_id', $doctor->id)->whereDate('checked_in_at', $schedule->work_date)->whereNotIn('status', ['COMPLETED', 'CLOSED', 'CANCELLED'])->exists()) {
            return 'Bác sĩ nhận ca đang có lịch hẹn hoặc lượt khám chưa kết thúc trong ngày này.';
        }

        return null;
    }

    public function sourceError(DoctorSchedule $schedule): ?string
    {
        if (! $schedule->doctor->is_active || ! $schedule->doctor->user->hasRole('doctor') || ! $schedule->doctor->user->is_active || $schedule->doctor->user->status !== 'ACTIVE'
            || ! $schedule->doctor->user->branches()->where('branches.id', $schedule->branch_id)->where('is_active', true)->exists()) {
            return 'Bác sĩ gửi đề nghị chưa hoạt động hoặc không còn được phân công tại chi nhánh này.';
        }
        if (! $schedule->is_open || ! Carbon::parse($schedule->work_date->format('Y-m-d').' '.$schedule->start_time)->isFuture()) {
            return 'Chỉ có thể đổi ca đang mở và chưa đến giờ bắt đầu.';
        }
        if ($schedule->appointments()->where(fn ($q) => $q->whereNotIn('status', ['PENDING', 'CONFIRMED', 'CANCELLED', 'NO_SHOW'])
            ->orWhereHas('visit')->orWhereHas('medicalRecord')->orWhereHas('invoice'))->exists()) {
            return 'Ca đã có người bệnh được tiếp nhận hoặc hồ sơ điều trị, không thể chuyển bác sĩ phụ trách.';
        }

        return null;
    }

    public function request(DoctorSchedule $schedule, int $receiverId, string $note): ShiftTransferRequest
    {
        $senderId = auth()->user()->doctor?->id;
        abort_unless(auth()->user()->hasRole('doctor') && $senderId === $schedule->doctor_id, 403);

        return DB::transaction(function () use ($schedule, $senderId, $receiverId, $note) {
            $this->lockDoctors([$senderId, $receiverId]);
            $shift = DoctorSchedule::lockForUpdate()->findOrFail($schedule->id);
            abort_unless($shift->doctor_id === $senderId, 403);
            $this->ensure($this->sourceError($shift));
            $receiver = Doctor::findOrFail($receiverId);
            $this->ensure($this->unavailableReason($receiver, $shift));
            $this->ensure(ShiftTransferRequest::where('pending_key', $shift->id)->exists() ? 'Ca này đã có đề nghị nhận ca đang chờ. Hãy hủy đề nghị cũ trước khi gửi lại.' : null);
            $transfer = ShiftTransferRequest::create([
                'doctor_schedule_id' => $shift->id, 'sender_doctor_id' => $senderId, 'receiver_doctor_id' => $receiverId,
                'branch_id' => $shift->branch_id, 'work_date' => $shift->work_date, 'start_time' => $shift->start_time,
                'end_time' => $shift->end_time, 'note' => $note, 'status' => 'PENDING', 'pending_key' => $shift->id,
            ]);
            AuditLog::record('shift.transfer_requested', $transfer);

            return $transfer;
        }, 5);
    }

    public function respond(ShiftTransferRequest $transfer, string $decision): void
    {
        $this->ensure(! in_array($decision, ['accept', 'reject', 'cancel'], true) ? 'Thao tác xử lý đề nghị không hợp lệ.' : null);
        $actor = auth()->user()->doctor?->id;
        abort_unless(auth()->user()->hasRole('doctor') && ($decision === 'cancel' ? $actor === $transfer->sender_doctor_id : $actor === $transfer->receiver_doctor_id), 403);
        DB::transaction(function () use ($transfer, $decision) {
            $this->lockDoctors([$transfer->sender_doctor_id, $transfer->receiver_doctor_id]);
            $request = ShiftTransferRequest::lockForUpdate()->findOrFail($transfer->id);
            $this->ensure($request->status !== 'PENDING' ? 'Đề nghị đã được xử lý. Vui lòng tải lại thông báo.' : null);
            $shift = DoctorSchedule::lockForUpdate()->findOrFail($request->doctor_schedule_id);
            if ($decision === 'accept') {
                $this->ensure(! $request->canRespond() || $shift->doctor_id !== $request->sender_doctor_id
                    || $shift->branch_id !== $request->branch_id || $shift->work_date->format('Y-m-d') !== $request->work_date->format('Y-m-d')
                    || substr($shift->start_time, 0, 5) !== substr($request->start_time, 0, 5) || substr($shift->end_time, 0, 5) !== substr($request->end_time, 0, 5)
                    ? 'Ca đã thay đổi hoặc quá giờ bắt đầu. Không thể nhận ca này.' : null);
                $this->ensure($this->sourceError($shift));
                $this->ensure($this->unavailableReason($request->receiver, $shift));
                $appointments = $shift->appointments()->lockForUpdate()->get();
                $shift->update(['doctor_id' => $request->receiver_doctor_id]);
                foreach ($appointments as $appointment) {
                    if (! in_array($appointment->status, ['PENDING', 'CONFIRMED'])) {
                        continue;
                    }
                    $old = $appointment->only(['doctor_id', 'doctor_schedule_id']);
                    $appointment->update(['doctor_id' => $request->receiver_doctor_id]);
                    DB::table('appointment_history')->insert([
                        'appointment_id' => $appointment->id, 'old_values' => json_encode($old),
                        'new_values' => json_encode($appointment->only(['doctor_id', 'doctor_schedule_id'])),
                        'reason' => mb_substr('Đổi bác sĩ phụ trách ca: '.$request->note, 0, 255), 'changed_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now(),
                    ]);
                    app(NotificationService::class)->appointment($appointment, 'DOCTOR_CHANGED_'.$request->id);
                }
            }
            $request->update(['status' => match ($decision) {
                'accept' => 'ACCEPTED', 'reject' => 'REJECTED', default => 'CANCELLED'
            }, 'pending_key' => null, 'responded_at' => now()]);
            AuditLog::record('shift.transfer_'.strtolower($request->status), $request);
        }, 5);
    }

    private function lockDoctors(array $ids): void
    {
        sort($ids);
        foreach (array_unique($ids) as $id) {
            app(AppointmentBookingService::class)->lockDoctor((int) $id);
        }
    }

    private function ensure(?string $error): void
    {
        if ($error !== null) {
            throw ValidationException::withMessages(['transfer' => $error]);
        }
    }
}
