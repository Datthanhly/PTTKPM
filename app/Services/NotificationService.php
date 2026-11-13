<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\NotificationJob;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    public function cancelReminders(int $id): void
    {
        NotificationJob::where('appointment_id', $id)->where('template', 'REMINDER')->whereIn('status', ['PENDING', 'RETRY'])->update(['status' => 'CANCELLED']);
    }

    public function appointment(Appointment $a, string $event): void
    {
        $a->loadMissing('patient', 'branch', 'doctor.user');
        if (! $a->patient->email_enabled || ! $a->patient->email) {
            return;
        }
        $eventLabel = str_starts_with($event, 'DOCTOR_CHANGED_') ? 'Bác sĩ phụ trách đã thay đổi; thời gian và chi nhánh giữ nguyên' : $event;
        $body = $a->branch->name.' | '.$a->appointment_code.' | '.$a->appointment_date->format('d/m/Y').' '.substr($a->start_time, 0, 5)
            .' | '.$a->doctor->user->name.' | '.$eventLabel.'. Liên hệ phòng khám để được hỗ trợ.';
        NotificationJob::firstOrCreate(['event_key' => $a->id.':'.$event.':'.($event === 'RESCHEDULED' ? bin2hex(random_bytes(8)) : 'once')], [
            'appointment_id' => $a->id, 'recipient' => $a->patient->email, 'template' => $event, 'body' => $body, 'scheduled_at' => now(),
        ]);
    }

    public function reminders(): void
    {
        foreach (Appointment::with(['patient', 'branch'])->whereIn('status', ['PENDING', 'CONFIRMED'])->whereDate('appointment_date', '>=', today())->whereDate('appointment_date', '<=', today()->addDays(2))->get() as $a) {
            if (! $a->patient->email_enabled || ! $a->patient->email) {
                continue;
            }
            $start = Carbon::parse($a->appointment_date->format('Y-m-d').' '.$a->start_time);
            foreach (config('clinic.reminder_hours') as $hours) {
                $due = $start->copy()->subHours($hours);
                if ($due->isPast() && $start->isFuture()) {
                    NotificationJob::firstOrCreate(['event_key' => $a->id.':REMINDER:'.$start->format('YmdHi').':'.$hours], [
                        'appointment_id' => $a->id, 'recipient' => $a->patient->email, 'template' => 'REMINDER',
                        'body' => $a->branch->name.' nhắc lịch '.$a->appointment_code.' lúc '.$start->format('H:i d/m/Y').'.', 'scheduled_at' => now(),
                    ]);
                }
            }
        }
    }

    public function process(): int
    {
        $count = 0;
        foreach (NotificationJob::whereIn('status', ['PENDING', 'RETRY'])->where('scheduled_at', '<=', now())->orderBy('id')->limit(100)->pluck('id') as $id) {
            DB::transaction(function () use ($id, &$count) {
                DB::table('notification_jobs')->where('id', $id)->update(['updated_at' => DB::raw('updated_at')]);
                $job = NotificationJob::lockForUpdate()->find($id);
                if (! in_array($job->status, ['PENDING', 'RETRY']) || $job->scheduled_at->isFuture()) {
                    return;
                }
                if ($job->appointment && ! $job->appointment->patient->email_enabled) {
                    $job->update(['status' => 'CANCELLED']);

                    return;
                }
                try {
                    Mail::raw($job->body, fn ($m) => $m->to($job->recipient)->subject('Thông báo phòng khám (demo)'));
                    $job->update(['status' => 'SENT', 'sent_at' => now(), 'error_code' => null]);
                    $count++;
                } catch (\Throwable $e) {
                    $attempt = $job->retry_count + 1;
                    $job->update(['status' => $attempt >= 5 ? 'FAILED' : 'RETRY', 'retry_count' => $attempt,
                        'scheduled_at' => now()->addMinutes(2 ** $attempt), 'error_code' => 'MAIL_TRANSPORT_ERROR']);
                }
            }, 5);
        }

        return $count;
    }
}
