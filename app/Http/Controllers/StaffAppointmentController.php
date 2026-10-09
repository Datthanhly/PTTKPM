<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class StaffAppointmentController extends Controller
{
    public function index(Request $request)
    {
        $query = Appointment::with(['patient', 'doctor.user', 'doctor.specialty', 'invoice'])->orderBy('appointment_date')->orderBy('start_time');
        if (auth()->user()->hasRole('doctor')) $query->where('doctor_id', auth()->user()->doctor?->id);
        if ($request->filled('date')) $query->whereDate('appointment_date', $request->date);
        else $query->whereDate('appointment_date', today());
        if ($request->filled('status')) $query->where('status', $request->status);
        $appointments = $query->paginate(20)->withQueryString();
        return view('staff.appointments', compact('appointments'));
    }

    public function transition(Request $request, Appointment $appointment)
    {
        $data = $request->validate(['status' => ['required', 'in:CONFIRMED,CHECKED_IN,IN_PROGRESS,CANCELLED,NO_SHOW']]);
        $role = auth()->user()->role->code;
        if ($role === 'doctor') abort_unless($appointment->doctor_id === auth()->user()->doctor?->id, 403);

        $allowed = [
            Appointment::PENDING => ['CONFIRMED', 'CANCELLED'],
            Appointment::CONFIRMED => ['CHECKED_IN', 'CANCELLED', 'NO_SHOW'],
            Appointment::CHECKED_IN => ['IN_PROGRESS'],
        ];
        abort_unless(in_array($data['status'], $allowed[$appointment->status] ?? [], true), 422, 'Chuyển trạng thái không hợp lệ.');
        if ($data['status'] === Appointment::IN_PROGRESS) abort_unless(in_array($role, ['doctor', 'admin'], true), 403);
        if (in_array($data['status'], ['CONFIRMED', 'CHECKED_IN', 'CANCELLED', 'NO_SHOW'], true)) abort_unless(in_array($role, ['receptionist', 'admin'], true), 403);

        $old = $appointment->status;
        $values = ['status' => $data['status']];
        if ($data['status'] === Appointment::CHECKED_IN) $values['checked_in_at'] = now();
        if ($data['status'] === Appointment::CANCELLED) $values['cancelled_at'] = now();
        $appointment->update($values);
        AuditLog::record('appointment.status_changed', $appointment, ['status' => $old], ['status' => $data['status']]);
        return back()->with('success', 'Đã cập nhật trạng thái lịch hẹn.');
    }
}
