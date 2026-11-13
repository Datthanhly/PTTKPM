<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\StaffScheduleRequest;
use App\Models\StaffWorkShift;
use App\Services\StaffRosterService;
use Illuminate\Http\Request;

class AdminRosterController extends Controller
{
    public function index(Request $request, StaffRosterService $roster)
    {
        $request->validate(['date' => ['nullable', 'date_format:Y-m-d'], 'branch_id' => ['nullable', 'integer'], 'focus' => ['nullable', 'integer']]);
        $date = $request->input('date') ?: today()->format('Y-m-d');
        $weekStart = $roster->week($date);
        $weekEnd = $weekStart->copy()->addDays(6);
        $branches = Branch::where('is_active', true)->orderBy('id')->get();
        $branchId = $request->filled('branch_id') ? $request->integer('branch_id') : null;
        abort_if($branchId && ! $branches->contains('id', $branchId), 404);
        $shifts = $roster->shifts($date, $branchId)->whereIn('status', StaffWorkShift::ACTIVE)->with(['user.role', 'branch', 'request'])->orderBy('work_date')->orderBy('start_time')->get();
        $drafts = $shifts->where('status', 'DRAFT');
        $planVersion = $roster->fingerprint($drafts);
        $registrationQuery = StaffScheduleRequest::with(['user.role', 'user.branches' => fn ($q) => $q->where('is_active', true)->orderBy('branches.id'), 'branch', 'shift.branch'])
            ->where(fn ($q) => $q->whereBetween('work_date', [$weekStart->format('Y-m-d'), $weekEnd->format('Y-m-d')])
                ->orWhereHas('shift', fn ($s) => $s->whereBetween('work_date', [$weekStart->format('Y-m-d'), $weekEnd->format('Y-m-d')])->whereIn('status', StaffWorkShift::ACTIVE)))
            ->when($branchId, fn ($q) => $q->where(fn ($b) => $b->where('branch_id', $branchId)->orWhereHas('shift', fn ($s) => $s->where('branch_id', $branchId))));
        $waitingCount = (clone $registrationQuery)->where('status', 'WAITING')->count();
        $registrations = $registrationQuery->when($request->filled('focus'), fn ($q) => $q->whereKey($request->integer('focus')))
            ->orderByRaw("CASE status WHEN 'WAITING' THEN 0 WHEN 'ARRANGED' THEN 1 WHEN 'CONFIRMED' THEN 2 ELSE 3 END")
            ->orderBy('work_date')->orderBy('start_time')->orderBy('id')->paginate(20)->withQueryString();

        return response()->view('staff-roster.admin', compact('date', 'weekStart', 'weekEnd', 'branches', 'branchId', 'shifts', 'drafts', 'planVersion', 'waitingCount', 'registrations'))
            ->header('Cache-Control', 'no-store, private');
    }

    public function autoArrange(Request $request, StaffRosterService $roster)
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'], 'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'receptionist_count' => ['required', 'integer', 'min:1', 'max:10'], 'cashier_count' => ['required', 'integer', 'min:1', 'max:10'],
        ]);
        $result = $roster->autoArrange($data['date'], $data['branch_id'] ?? null, ['receptionist' => (int) $data['receptionist_count'], 'cashier' => (int) $data['cashier_count']]);

        return redirect()->route('admin.roster.index', ['date' => $data['date'], 'branch_id' => $data['branch_id'] ?? null])->with('rosterResult', $result)
            ->with('success', 'Đã xếp '.$result['created'].' ca dự kiến. Hãy xem lại và bấm Chốt lịch để công bố cho nhân viên.');
    }

    public function arrange(Request $request, StaffScheduleRequest $registration, StaffRosterService $roster)
    {
        $data = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'], 'work_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today', 'before_or_equal:'.today()->addDays(config('staff_roster.max_registration_days'))->format('Y-m-d')],
            'start_time' => ['required', 'date_format:H:i'], 'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'note' => ['nullable', 'string', 'max:1000'], 'plan_version' => ['required', 'string', 'regex:/^(new|[a-f0-9]{64})$/'],
        ]);
        $shift = $roster->arrange($registration, $data);

        return redirect()->route('admin.roster.index', ['date' => $shift->work_date->format('Y-m-d'), 'branch_id' => $shift->branch_id])->with('success', 'Đã lưu ca dự kiến. Ca chỉ trở thành lịch chính thức sau khi chốt.');
    }

    public function publish(Request $request, StaffRosterService $roster)
    {
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d'], 'branch_id' => ['nullable', 'integer', 'exists:branches,id'], 'plan_version' => ['required', 'string', 'size:64']]);
        $count = $roster->publish($data['date'], $data['branch_id'] ?? null, $data['plan_version']);

        return redirect()->route('admin.roster.index', ['date' => $data['date'], 'branch_id' => $data['branch_id'] ?? null])->with('success', 'Đã chốt '.$count.' ca. Nhân viên đã có thể xem lịch chính thức.');
    }

    public function reject(Request $request, StaffScheduleRequest $registration, StaffRosterService $roster)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $roster->reject($registration, $data['reason']);

        return redirect()->route('admin.roster.index', ['date' => $registration->work_date->format('Y-m-d')])->with('success', 'Đã từ chối đăng ký và bỏ ca dự kiến liên quan.');
    }

    public function reopen(Request $request, StaffWorkShift $shift, StaffRosterService $roster)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000'], 'plan_version' => ['required', 'string', 'size:64']]);
        $roster->reopen($shift, $data['reason'], $data['plan_version']);

        return redirect()->route('admin.roster.index', ['date' => $shift->work_date->format('Y-m-d'), 'branch_id' => $shift->branch_id])->with('success', 'Đã mở chốt ca. Ca chuyển về dự kiến và cần chốt lại sau khi điều chỉnh.');
    }
}
