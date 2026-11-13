<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Specialty;
use App\Services\AppointmentBookingService;
use App\Services\ClinicAccess;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    public function index()
    {
        $patient = auth()->user()->patient;
        abort_unless($patient, 403);
        $appointments = $patient->appointments()->with(['branch', 'doctor.user', 'doctor.specialty', 'medicalRecord', 'invoice', 'visit'])->latest('appointment_date')->paginate(10);

        return view('appointments.index', compact('appointments'));
    }

    public function create()
    {
        if (! auth()->user()->hasAnyRole(['patient', 'receptionist'])) {
            return redirect()->route('dashboard')->with('info', 'Đặt lịch khám dành cho tài khoản Bệnh nhân hoặc Lễ tân. Để tự đặt lịch, hãy đăng xuất và đăng nhập bằng tài khoản Bệnh nhân.');
        }

        $specialties = Specialty::where('is_active', true)->orderBy('name')->get();
        $doctors = Doctor::with(['user.branches', 'specialty', 'specialties', 'schedules' => fn ($q) => $q->whereDate('work_date', '>=', today())->where('is_open', true)->orderBy('work_date')])
            ->where('is_active', true)->get();
        $branches = ClinicAccess::branches();
        $patients = auth()->user()->hasRole('patient') ? collect() : ClinicAccess::patients()->where('is_active', true)
            ->when(request()->filled('patient_id'), fn ($q) => $q->whereKey(request()->integer('patient_id')))
            ->when(request()->filled('patient_q'), fn ($q) => $q->where(fn ($p) => $p->where('patient_code', 'like', '%'.request('patient_q').'%')->orWhere('full_name', 'like', '%'.request('patient_q').'%')->orWhere('phone', 'like', '%'.request('patient_q').'%')))
            ->orderBy('full_name')->limit(100)->get();

        return view('appointments.create', compact('specialties', 'doctors', 'branches', 'patients'));
    }

    public function store(Request $request, AppointmentBookingService $booking)
    {
        $data = $request->validate([
            'branch_id' => ['required', 'exists:branches,id'], 'patient_id' => ['nullable', 'exists:patients,id'],
            'idempotency_key' => ['required', 'string', 'max:100'],
            'doctor_id' => ['required', 'exists:doctors,id'], 'appointment_date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'], 'reason' => ['required', 'string', 'max:2000'],
        ]);
        $patient = auth()->user()->hasRole('patient') ? auth()->user()->patient : ClinicAccess::patients()->findOrFail($data['patient_id'] ?? 0);
        abort_unless($patient, 403);
        if (! auth()->user()->hasRole('patient')) {
            ClinicAccess::require('APPOINTMENT_CREATE', (int) $data['branch_id']);
        }
        $appointment = $booking->book($patient, Doctor::findOrFail($data['doctor_id']), $data['appointment_date'], $data['start_time'], $data['reason'], auth()->user(), (int) $data['branch_id'], $data['idempotency_key']);

        return redirect()->route(auth()->user()->hasRole('patient') ? 'appointments.index' : 'staff.appointments')->with('success', "Đã tạo lịch {$appointment->appointment_code}.");
    }

    public function cancel(Request $request, Appointment $appointment)
    {
        $this->authorizeAppointment($appointment);
        $data = $request->validate(['cancel_reason' => ['required', 'string', 'max:1000']]);
        app(AppointmentBookingService::class)->cancel($appointment, $data['cancel_reason'], auth()->user());

        return back()->with('success', 'Đã hủy lịch khám.');
    }

    public function availability(Request $request, AppointmentBookingService $booking)
    {
        $data = $request->validate(['doctor_id' => ['required', 'exists:doctors,id'], 'branch_id' => ['required', 'exists:branches,id'], 'date' => ['required', 'date_format:Y-m-d'], 'appointment_id' => ['nullable', 'exists:appointments,id']]);
        if (! auth()->user()->hasRole('patient')) {
            ClinicAccess::require('APPOINTMENT_CREATE', (int) $data['branch_id']);
        }
        $exclude = null;
        if (! empty($data['appointment_id'])) {
            $a = Appointment::findOrFail($data['appointment_id']);
            $this->authorizeAppointment($a);
            $exclude = $a->id;
        }

        return response()->json(['slots' => $booking->availability(Doctor::findOrFail($data['doctor_id']), (int) $data['branch_id'], $data['date'], $exclude)]);
    }

    public function edit(Appointment $appointment)
    {
        $this->authorizeAppointment($appointment);

        return view('appointments.reschedule', compact('appointment'));
    }

    public function reschedule(Request $request, Appointment $appointment, AppointmentBookingService $booking)
    {
        $this->authorizeAppointment($appointment);
        $data = $request->validate(['appointment_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'], 'start_time' => ['required', 'date_format:H:i'], 'reason' => ['required', 'string', 'max:255']]);
        $booking->reschedule($appointment, $data['appointment_date'], $data['start_time'], $data['reason'], auth()->user());

        return redirect()->route(auth()->user()->hasRole('patient') ? 'appointments.index' : 'staff.appointments')->with('success', 'Đã đổi lịch và lưu lịch sử.');
    }

    private function authorizeAppointment(Appointment $appointment): void
    {
        if (auth()->user()->hasRole('patient')) {
            abort_unless($appointment->patient_id === auth()->user()->patient?->id, 403);
        } else {
            ClinicAccess::require('APPOINTMENT_RESCHEDULE', $appointment->branch_id);
        }
    }
}
