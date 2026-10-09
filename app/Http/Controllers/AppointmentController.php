<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Doctor;
use App\Models\Specialty;
use App\Services\AppointmentBookingService;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    public function index()
    {
        $patient = auth()->user()->patient;
        abort_unless($patient, 403);
        $appointments = $patient->appointments()->with(['doctor.user', 'doctor.specialty', 'medicalRecord', 'invoice'])->latest('appointment_date')->paginate(10);
        return view('appointments.index', compact('appointments'));
    }

    public function create()
    {
        $specialties = Specialty::where('is_active', true)->orderBy('name')->get();
        $doctors = Doctor::with(['user', 'specialty', 'schedules' => fn ($q) => $q->whereDate('work_date', '>=', today())->where('is_open', true)->orderBy('work_date')])
            ->where('is_active', true)->get();
        return view('appointments.create', compact('specialties', 'doctors'));
    }

    public function store(Request $request, AppointmentBookingService $booking)
    {
        $data = $request->validate([
            'doctor_id' => ['required', 'exists:doctors,id'], 'appointment_date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'], 'reason' => ['required', 'string', 'max:2000'],
        ]);
        $patient = auth()->user()->patient;
        abort_unless($patient, 403);
        $appointment = $booking->book($patient, Doctor::findOrFail($data['doctor_id']), $data['appointment_date'], $data['start_time'], $data['reason'], auth()->user());
        AuditLog::record('appointment.created', $appointment, [], $appointment->only(['status', 'appointment_date', 'start_time']));
        return redirect()->route('appointments.index')->with('success', "Đã tạo lịch {$appointment->appointment_code}.");
    }

    public function cancel(Request $request, Appointment $appointment)
    {
        abort_unless($appointment->patient_id === auth()->user()->patient?->id, 403);
        abort_unless(in_array($appointment->status, [Appointment::PENDING, Appointment::CONFIRMED], true), 422, 'Lịch không thể hủy ở trạng thái hiện tại.');
        $startsAt = \Carbon\Carbon::parse($appointment->appointment_date->format('Y-m-d').' '.$appointment->start_time);
        abort_if(now()->diffInHours($startsAt, false) < 12, 422, 'Chỉ được hủy trước giờ khám ít nhất 12 giờ.');
        $data = $request->validate(['cancel_reason' => ['required', 'string', 'max:1000']]);
        $old = $appointment->status;
        $appointment->update(['status' => Appointment::CANCELLED, 'cancel_reason' => $data['cancel_reason'], 'cancelled_at' => now()]);
        AuditLog::record('appointment.cancelled', $appointment, ['status' => $old], ['status' => $appointment->status]);
        return back()->with('success', 'Đã hủy lịch khám.');
    }
}
