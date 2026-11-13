<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\ShiftTransferRequest;
use App\Models\StaffScheduleRequest;
use App\Services\ClinicAccess;
use App\Services\ReportService;
use App\Services\StaffRosterService;

class DashboardController extends Controller
{
    public function __invoke(ReportService $reports)
    {
        $user = auth()->user()->load('role', 'patient', 'doctor');
        $query = Appointment::with(['branch', 'patient', 'doctor.user', 'doctor.specialty'])->orderBy('appointment_date')->orderBy('start_time');
        if ($user->hasRole('patient')) {
            $query->where('patient_id', $user->patient?->id);
        } else {
            ClinicAccess::scope($query);
            if ($user->hasRole('doctor')) {
                $query->where('doctor_id', $user->doctor?->id);
            }
        }
        $appointments = $query->whereDate('appointment_date', '>=', today())->whereNotIn('status', ['CANCELLED', 'NO_SHOW'])->limit(12)->get();
        $stats = null;
        if ($user->hasPermission('REPORT_VIEW')) {
            [$from, $to] = $reports->range();
            $stats = $reports->summary($from, $to);
        }

        $incomingTransfers = collect();
        $outgoingTransfers = collect();
        if ($user->hasRole('doctor') && $user->doctor) {
            $base = ShiftTransferRequest::with(['sender.user', 'receiver.user', 'branch'])->orderByRaw("CASE WHEN status = 'PENDING' THEN 0 ELSE 1 END")->latest('id');
            $incomingTransfers = (clone $base)->where('receiver_doctor_id', $user->doctor->id)->limit(20)->get();
            $outgoingTransfers = (clone $base)->where('sender_doctor_id', $user->doctor->id)->limit(20)->get();
        }

        $rosterNotice = null;
        if ($user->hasAnyRole(['admin', 'receptionist', 'cashier'])) {
            $requests = StaffScheduleRequest::whereIn('status', ['WAITING', 'ARRANGED'])->when(! $user->hasRole('admin'), fn ($q) => $q->where('user_id', $user->id));
            $upcoming = app(StaffRosterService::class)->confirmedUpcoming()->when(! $user->hasRole('admin'), fn ($q) => $q->where('user_id', $user->id));
            $rosterNotice = [
                'waiting' => (clone $requests)->where('status', 'WAITING')->count(),
                'arranged' => (clone $requests)->where('status', 'ARRANGED')->count(),
                'confirmed' => (clone $upcoming)->count(),
                'date' => (clone $requests)->orderBy('work_date')->value('work_date') ?? (clone $upcoming)->orderBy('work_date')->value('work_date') ?? today()->format('Y-m-d'),
            ];
        }

        return response()->view('dashboard', ['user' => $user, 'appointments' => $appointments, 'stats' => $stats, 'branches' => ClinicAccess::branches(), 'incomingTransfers' => $incomingTransfers, 'outgoingTransfers' => $outgoingTransfers, 'rosterNotice' => $rosterNotice])->header('Cache-Control', 'no-store, private');
    }
}
