<?php

namespace App\Http\Controllers;

use App\Models\StaffScheduleRequest;
use App\Services\ClinicAccess;
use App\Services\StaffRosterService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EmployeeScheduleController extends Controller
{
    public function index(Request $request, StaffRosterService $roster)
    {
        $request->validate(['date' => ['nullable', 'date_format:Y-m-d'], 'branch_id' => ['nullable', 'integer']]);
        $date = $request->input('date') ?: today()->format('Y-m-d');
        $weekStart = $roster->week($date);
        $weekEnd = $weekStart->copy()->addDays(6);
        $branches = ClinicAccess::branches();
        $branchId = $request->filled('branch_id') ? $request->integer('branch_id') : null;
        abort_if($branchId && ! $branches->contains('id', $branchId), 404);
        $shifts = $roster->shifts($date, $branchId)->where('user_id', $request->user()->id)->where('status', 'CONFIRMED')
            ->with(['user.role', 'branch'])->orderBy('work_date')->orderBy('start_time')->get();
        $registrations = StaffScheduleRequest::with(['branch', 'shift.branch'])->where('user_id', $request->user()->id)
            ->where(fn ($q) => $q->whereBetween('work_date', [$weekStart->format('Y-m-d'), $weekEnd->format('Y-m-d')])
                ->orWhereHas('shift', fn ($s) => $s->whereBetween('work_date', [$weekStart->format('Y-m-d'), $weekEnd->format('Y-m-d')])->whereIn('status', ['DRAFT', 'CONFIRMED'])))
            ->when($branchId, fn ($q) => $q->where(fn ($b) => $b->where('branch_id', $branchId)->orWhereHas('shift', fn ($s) => $s->where('branch_id', $branchId))))
            ->orderBy('work_date')->orderBy('start_time')->paginate(20)->withQueryString();

        return response()->view('staff-roster.employee', compact('date', 'weekStart', 'weekEnd', 'branches', 'branchId', 'shifts', 'registrations'))
            ->header('Cache-Control', 'no-store, private');
    }

    public function store(Request $request, StaffRosterService $roster)
    {
        $limit = today()->addDays(config('staff_roster.max_registration_days'))->format('Y-m-d');
        $data = $request->validateWithBag('staffRegistration', [
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'from_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today', 'before_or_equal:'.$limit],
            'to_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:from_date', 'before_or_equal:'.$limit],
            'weekdays' => ['required', 'array', 'min:1', 'max:7'], 'weekdays.*' => ['required', 'integer', 'distinct', 'between:1,7'],
            'session' => ['required', 'in:morning,afternoon,both'], 'note' => ['nullable', 'string', 'max:1000'],
        ]);
        try {
            $created = $roster->submit($data);
        } catch (ValidationException $exception) {
            throw $exception->errorBag('staffRegistration');
        }

        return redirect()->route('employee-schedules.index', ['date' => $created->first()->work_date->format('Y-m-d')])
            ->with('success', 'Đã gửi '.$created->count().' ca đăng ký ở trạng thái WAITING. Admin sẽ sắp xếp và chốt lịch chính thức.');
    }

    public function withdraw(StaffScheduleRequest $registration, StaffRosterService $roster)
    {
        $date = $registration->shift?->work_date ?? $registration->work_date;
        $roster->withdraw($registration);

        return redirect()->route('employee-schedules.index', ['date' => $date->format('Y-m-d')])->with('success', 'Đã rút đăng ký chưa chốt.');
    }
}
