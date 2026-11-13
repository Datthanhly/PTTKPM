<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\ShiftTransferRequest;
use App\Services\AppointmentBookingService;
use App\Services\ClinicAccess;
use App\Services\NotificationService;
use App\Services\ShiftTransferService;
use App\Services\VisitWorkflow;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class StaffAppointmentController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['date' => ['nullable', 'date_format:Y-m-d'], 'branch_id' => ['nullable', 'integer'], 'doctor_id' => ['nullable', 'integer']]);
        $date = Carbon::parse($request->input('date') ?: today()->format('Y-m-d'));
        $weekStart = $date->copy()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->copy()->addDays(6);
        $days = collect(range(0, 6))->map(fn ($offset) => $weekStart->copy()->addDays($offset));
        $branches = ClinicAccess::branches();
        if ($request->filled('branch_id')) {
            abort_unless($branches->contains('id', $request->integer('branch_id')), 404);
        }
        $doctors = Doctor::with(['user.branches' => fn ($q) => $q->whereIn('branches.id', $branches->pluck('id'))])->where('is_active', true)
            ->whereHas('user', fn ($q) => $q->where('is_active', true)->where('status', 'ACTIVE'))
            ->whereHas('user.branches', fn ($q) => $q->whereIn('branches.id', $branches->pluck('id')))
            ->when(auth()->user()->hasRole('doctor'), fn ($q) => $q->whereKey(auth()->user()->doctor?->id))
            ->orderBy('doctor_code')->get();
        $selectedDoctor = $request->filled('doctor_id') ? $doctors->firstWhere('id', $request->integer('doctor_id')) : $doctors->first();
        abort_if($request->filled('doctor_id') && ! $selectedDoctor, 404);
        $schedules = $this->scheduleQuery()->where('doctor_id', $selectedDoctor?->id)
            ->whereDate('work_date', '>=', $weekStart)->whereDate('work_date', '<=', $weekEnd)
            ->with(['branch', 'doctor.user'])->withCount(['appointments' => fn ($q) => $q->whereColumn('appointments.doctor_id', 'doctor_schedules.doctor_id')])->orderBy('work_date')->orderBy('start_time')->get();

        $startHour = 7;
        $endHour = 18;
        foreach ($schedules as $shift) {
            $startHour = min($startHour, (int) substr($shift->start_time, 0, 2));
            $endMinutes = (int) substr($shift->end_time, 0, 2) * 60 + (int) substr($shift->end_time, 3, 2);
            $endHour = max($endHour, (int) ceil($endMinutes / 60));
        }

        return view('staff.appointments', compact('branches', 'doctors', 'selectedDoctor', 'date', 'weekStart', 'weekEnd', 'days', 'schedules', 'startHour', 'endHour'));
    }

    public function shift(Request $request, DoctorSchedule $schedule, ShiftTransferService $transfers)
    {
        // Authorize before loading patient details, including direct/async requests.
        abort_unless($this->scheduleQuery()->whereKey($schedule->id)->exists(), 404);
        $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:PENDING,CONFIRMED,CHECKED_IN,IN_PROGRESS,COMPLETED,CANCELLED,NO_SHOW'],
        ]);
        $schedule->load(['branch', 'doctor.user']);
        $transferError = null;
        $transferCandidates = collect();
        $pendingTransfer = null;
        if ($request->user()->hasRole('doctor')) {
            $transferError = $transfers->sourceError($schedule);
            $pendingTransfer = ShiftTransferRequest::with('receiver.user')->where('pending_key', $schedule->id)->first();
            if (! $transferError && ! $pendingTransfer) {
                $transferCandidates = $transfers->candidates($schedule);
            }
        }
        $appointments = $schedule->appointments()->with(['branch', 'patient', 'doctor.user', 'doctor.specialty', 'invoice', 'visit'])
            ->where('doctor_id', $schedule->doctor_id)->where('branch_id', $schedule->branch_id)
            ->whereDate('appointment_date', $schedule->work_date)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->input('q').'%';
                $query->where(fn ($q) => $q->where('appointment_code', 'like', $term)
                    ->orWhereHas('patient', fn ($p) => $p->where('full_name', 'like', $term)->orWhere('patient_code', 'like', $term)->orWhere('phone', 'like', $term)));
            })->orderBy('start_time')->orderBy('id')->paginate(20)->withQueryString();

        return response()->view($request->ajax() ? 'staff.partials.shift-appointments' : 'staff.shift', compact('schedule', 'appointments', 'transferError', 'transferCandidates', 'pendingTransfer'))
            ->header('Cache-Control', 'no-store, private');
    }

    private function scheduleQuery(): Builder
    {
        return ClinicAccess::scope(DoctorSchedule::query())
            ->when(auth()->user()->hasRole('doctor'), fn ($q) => $q->where('doctor_id', auth()->user()->doctor?->id));
    }

    public function transition(Request $request, Appointment $appointment, VisitWorkflow $workflow)
    {
        try {
            return $this->applyTransition($request, $appointment, $workflow);
        } catch (HttpExceptionInterface $exception) {
            if (! $request->expectsJson() && in_array($exception->getStatusCode(), [409, 422], true)) {
                throw ValidationException::withMessages(['status' => $exception->getMessage()]);
            }
            throw $exception;
        }
    }

    private function applyTransition(Request $request, Appointment $appointment, VisitWorkflow $workflow)
    {
        $data = $request->validate(['status' => ['required', 'in:CONFIRMED,CHECKED_IN,IN_PROGRESS,CANCELLED,NO_SHOW'], 'cancel_reason' => ['required_if:status,CANCELLED', 'nullable', 'string', 'max:255']]);
        if ($data['status'] === 'IN_PROGRESS') {
            abort_unless($appointment->visit, 422, 'Chưa có lượt khám.');
            $workflow->transition($appointment->visit, 'IN_PROGRESS');

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Đã bắt đầu khám.', 'redirect' => route('visits.show', $appointment->visit)]);
            }

            return redirect()->route('visits.show', $appointment->visit);
        }
        ClinicAccess::require('VISIT_CHECK_IN', $appointment->branch_id);
        if ($data['status'] === 'CHECKED_IN') {
            $workflow->checkIn($appointment);
        } elseif ($data['status'] === 'CANCELLED') {
            app(AppointmentBookingService::class)->cancel($appointment, $data['cancel_reason'], auth()->user());
        } else {
            DB::transaction(function () use ($appointment, $data) {
                app(AppointmentBookingService::class)->lockDoctor($appointment->doctor_id);
                $a = Appointment::lockForUpdate()->findOrFail($appointment->id);
                ClinicAccess::require('VISIT_CHECK_IN', $a->branch_id);
                abort_if($a->doctor_id !== $appointment->doctor_id, 409, 'Bác sĩ phụ trách đã thay đổi. Vui lòng tải lại lịch trước khi cập nhật.');
                abort_unless(($a->status === 'PENDING' && $data['status'] === 'CONFIRMED') || ($a->status === 'CONFIRMED' && $data['status'] === 'NO_SHOW'), 409, 'Chuyển trạng thái không hợp lệ.');
                if ($data['status'] === 'NO_SHOW') {
                    abort_if(\Carbon\Carbon::parse($a->appointment_date->format('Y-m-d').' '.$a->end_time)->isFuture(), 422, 'Chưa hết giờ hẹn.');
                }
                $a->update(['status' => $data['status']]);
                if ($data['status'] === 'CONFIRMED') {
                    app(NotificationService::class)->appointment($a, 'CONFIRMED');
                } else {
                    app(NotificationService::class)->cancelReminders($a->id);
                }
                AuditLog::record('appointment.status_changed', $a, [], ['status' => $data['status']]);
            }, 5);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Đã cập nhật lịch hẹn.']);
        }

        return back()->with('success', 'Đã cập nhật lịch hẹn.');
    }
}
