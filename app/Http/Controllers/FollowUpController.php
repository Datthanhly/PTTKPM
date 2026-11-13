<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\FollowUpPlan;
use App\Models\NotificationJob;
use App\Models\Visit;
use App\Services\ClinicAccess;
use App\Services\FollowUpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FollowUpController extends Controller
{
    public function index()
    {
        $plans = FollowUpPlan::whereHas('visit', fn ($q) => $q->where('patient_id', auth()->user()->patient?->id))->with(['visit.branch', 'visit.doctor.user', 'appointment'])->latest()->paginate(10);

        return view('patients.follow-ups', compact('plans'));
    }

    public function store(Request $request, Visit $visit)
    {
        ClinicAccess::treat($visit);
        $data = $request->validate(['suggested_from' => ['required', 'date_format:Y-m-d', 'after:today'], 'suggested_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:suggested_from', 'before_or_equal:'.today()->addYear()->format('Y-m-d')], 'reason' => ['required', 'string', 'max:255']]);
        DB::transaction(function () use ($visit, $data) {
            $p = $visit->followUps()->create($data);
            if ($visit->patient->email_enabled && $visit->patient->email) {
                NotificationJob::create(['event_key' => 'FOLLOW_UP:'.$p->id, 'recipient' => $visit->patient->email,
                    'template' => 'FOLLOW_UP_PROPOSED', 'body' => 'Bạn có đề xuất tái khám mới. Đăng nhập hệ thống phòng khám để chọn lịch hoặc từ chối.', 'scheduled_at' => now()]);
            }
            AuditLog::record('follow_up.proposed', $visit, [], ['plan_id' => $p->id]);
        });

        return back()->with('success', 'Đã đề xuất tái khám, chưa tự đặt nếu thiếu đồng ý.');
    }

    public function respond(Request $request, FollowUpPlan $plan, FollowUpService $followUps)
    {
        abort_unless($plan->visit->patient_id === auth()->user()->patient?->id, 403);
        $data = $request->validate(['action' => ['required', 'in:accept,decline'], 'date' => ['required_if:action,accept', 'nullable', 'date_format:Y-m-d'], 'start_time' => ['required_if:action,accept', 'nullable', 'date_format:H:i']]);
        if ($data['action'] === 'accept') {
            $followUps->schedule($plan, $data['date'], $data['start_time']);
        } else {
            DB::transaction(function () use ($plan) {
                $p = FollowUpPlan::lockForUpdate()->findOrFail($plan->id);
                abort_unless($p->status === 'PROPOSED', 409);
                $p->update(['status' => 'DECLINED']);
                AuditLog::record('follow_up.declined', $p->visit, [], ['plan_id' => $p->id]);
            });
        }

        return back()->with('success', 'Đã ghi nhận phản hồi tái khám.');
    }
}
