<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\FollowUpPlan;
use App\Models\Patient;
use Illuminate\Support\Facades\DB;

class FollowUpService
{
    public function schedule(FollowUpPlan $plan, ?string $date = null, ?string $time = null, bool $automatic = false): void
    {
        DB::transaction(function () use ($plan, $date, $time, $automatic) {
            $p = FollowUpPlan::lockForUpdate()->findOrFail($plan->id);
            if ($p->status === 'SCHEDULED') {
                return;
            }
            abort_unless($p->status === 'PROPOSED', 409, 'Đề xuất không còn hiệu lực.');
            $v = $p->visit;
            $patient = Patient::lockForUpdate()->findOrFail($v->patient_id);
            if ($automatic) {
                if (! $patient->auto_booking_consented_at || ! $patient->auto_booking_expires_at?->isFuture()) {
                    return;
                }
                // Consent covers the earliest available slot within the proposed window.
                $date = null;
                for ($day = $p->suggested_from->copy()->max(today()); $day->lte($p->suggested_to); $day->addDay()) {
                    $slots = app(AppointmentBookingService::class)->availability($v->doctor, $v->branch_id, $day->format('Y-m-d'));
                    foreach ($slots as $slot) {
                        $conflict = $patient->appointments()->whereDate('appointment_date', $day)->whereNotIn('status', ['CANCELLED', 'NO_SHOW'])
                            ->where('start_time', '<', $slot['end_time'].':00')->where('end_time', '>', $slot['start_time'].':00')->exists();
                        if (! $conflict) {
                            $date = $day->format('Y-m-d');
                            $time = $slot['start_time'];
                            break 2;
                        }
                    }
                }
                if (! $date) {
                    return;
                }
            }
            abort_unless($date && $date >= $p->suggested_from->format('Y-m-d') && $date <= $p->suggested_to->format('Y-m-d'), 422, 'Ngày ngoài khoảng tái khám.');
            $a = app(AppointmentBookingService::class)->book($patient, $v->doctor, $date, $time, 'Tái khám theo đề xuất #'.$p->id, $patient->user, $v->branch_id, 'follow-up:'.$p->id);
            $p->update(['status' => 'SCHEDULED', 'appointment_id' => $a->id]);
            AuditLog::record('follow_up.scheduled', $v, [], ['plan_id' => $p->id, 'appointment_id' => $a->id, 'automatic' => $automatic]);
        }, 5);
    }

    public function process(): void
    {
        FollowUpPlan::where('status', 'PROPOSED')->whereDate('suggested_to', '<', today())->update(['status' => 'EXPIRED']);
        foreach (FollowUpPlan::where('status', 'PROPOSED')->whereHas('visit.patient', fn ($q) => $q->whereNotNull('auto_booking_consented_at')->where('auto_booking_expires_at', '>', now()))->get() as $plan) {
            try {
                $this->schedule($plan, null, null, true);
            } catch (\Illuminate\Validation\ValidationException $e) { /* Slot may have filled; retry next run. */
            }
        }
    }
}
