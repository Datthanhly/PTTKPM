<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\User;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $user = auth()->user()->load('role', 'patient', 'doctor');
        $query = Appointment::with(['patient', 'doctor.user', 'doctor.specialty'])->orderBy('appointment_date')->orderBy('start_time');

        if ($user->hasRole('patient')) {
            $query->where('patient_id', $user->patient?->id);
        } elseif ($user->hasRole('doctor')) {
            $query->where('doctor_id', $user->doctor?->id);
        }

        $appointments = $query->whereDate('appointment_date', '>=', today())->limit(12)->get();
        $stats = [
            'patients' => Patient::count(), 'doctors' => Doctor::where('is_active', true)->count(),
            'today' => Appointment::whereDate('appointment_date', today())->count(),
            'unpaid' => Invoice::where('status', '!=', 'PAID')->sum('total'),
        ];

        return view('dashboard', compact('user', 'appointments', 'stats'));
    }
}
