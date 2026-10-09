<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Patient;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AppointmentBookingService
{
    public function book(Patient $patient, Doctor $doctor, string $date, string $startTime, string $reason, ?User $creator = null): Appointment
    {
        return DB::transaction(function () use ($patient, $doctor, $date, $startTime, $reason, $creator) {
            $start = Carbon::parse("{$date} {$startTime}");
            if ($start->isPast()) {
                throw ValidationException::withMessages(['start_time' => 'Không thể đặt lịch trong quá khứ.']);
            }

            $schedule = DoctorSchedule::query()
                ->where('doctor_id', $doctor->id)->whereDate('work_date', $date)->where('is_open', true)
                ->where('start_time', '<=', $start->format('H:i:s'))
                ->orderBy('start_time')->lockForUpdate()->get()
                ->first(fn (DoctorSchedule $item) => $start->copy()->addMinutes($item->slot_minutes)
                    ->lte(Carbon::parse("{$date} {$item->end_time}")));

            if (!$schedule) {
                throw ValidationException::withMessages(['start_time' => 'Khung giờ không nằm trong ca làm việc đang mở của bác sĩ.']);
            }

            $minutesFromShiftStart = Carbon::parse($schedule->start_time)->diffInMinutes(Carbon::parse($startTime), false);
            if ($minutesFromShiftStart < 0 || $minutesFromShiftStart % $schedule->slot_minutes !== 0) {
                throw ValidationException::withMessages(['start_time' => "Giờ khám phải theo bước {$schedule->slot_minutes} phút."]);
            }

            $end = $start->copy()->addMinutes($schedule->slot_minutes);
            $conflict = Appointment::query()
                ->where('doctor_id', $doctor->id)->whereDate('appointment_date', $date)
                ->whereIn('status', Appointment::ACTIVE_STATUSES)
                ->where('start_time', '<', $end->format('H:i:s'))
                ->where('end_time', '>', $start->format('H:i:s'))
                ->lockForUpdate()->exists();

            if ($conflict) {
                throw ValidationException::withMessages(['start_time' => 'Khung giờ này vừa được đặt. Vui lòng chọn giờ khác.']);
            }

            return Appointment::create([
                'appointment_code' => 'LH'.now()->format('ymd').strtoupper(substr(bin2hex(random_bytes(4)), 0, 6)),
                'patient_id' => $patient->id, 'doctor_id' => $doctor->id,
                'doctor_schedule_id' => $schedule->id, 'created_by' => $creator?->id,
                'appointment_date' => $date, 'start_time' => $start->format('H:i:s'),
                'end_time' => $end->format('H:i:s'), 'reason' => $reason,
                'status' => Appointment::PENDING,
            ]);
        }, 5);
    }
}
