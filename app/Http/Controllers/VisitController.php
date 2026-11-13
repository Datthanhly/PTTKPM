<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Medicine;
use App\Models\Prescription;
use App\Models\Service;
use App\Models\Visit;
use App\Services\ClinicAccess;
use App\Services\VisitWorkflow;
use Illuminate\Http\Request;

class VisitController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        abort_unless($user->hasAnyRole(['doctor', 'receptionist', 'cashier']) || $user->hasPermission('MEDICINE_DISPENSE'), 403);
        $query = ClinicAccess::scope(Visit::with(['patient', 'doctor.user', 'branch', 'invoice'])->latest('checked_in_at'));
        if ($user->hasRole('doctor')) {
            $query->where('doctor_id', $user->doctor?->id);
        }
        if (request()->filled('date')) {
            $query->whereDate('checked_in_at', request('date'));
        }
        if (request()->filled('status')) {
            $query->where('status', request('status'));
        }
        $visits = $query->paginate(20)->withQueryString();

        return view('visits.index', ['visits' => $visits, 'branches' => ClinicAccess::branches()]);
    }

    public function show(Visit $visit)
    {
        ClinicAccess::treat($visit);
        $visit->load(['patient', 'branch', 'medicalRecord', 'prescriptions.items.medicine', 'serviceOrders.items.service', 'serviceOrders.items.results', 'measurements', 'followUps']);
        AuditLog::record('record.accessed', $visit);
        $history = Visit::where('patient_id', $visit->patient_id)->where('branch_id', $visit->branch_id)->where('id', '!=', $visit->id)
            ->whereHas('medicalRecord', fn ($q) => $q->where('status', 'SIGNED'))->with('medicalRecord')->latest('checked_in_at')->limit(10)->get();

        return view('visits.show', ['visit' => $visit, 'history' => $history, 'medicines' => Medicine::where('is_active', true)->orderBy('name')->get(), 'services' => Service::where('is_active', true)->orderBy('name')->get()]);
    }

    public function transition(Request $request, Visit $visit, VisitWorkflow $workflow)
    {
        $data = $request->validate(['status' => ['required', 'in:IN_PROGRESS,WAITING_RESULT,COMPLETED,CLOSED,CANCELLED']]);
        $workflow->transition($visit, $data['status']);

        return back()->with('success', 'Đã cập nhật lượt khám.');
    }

    public function record(Request $request, Visit $visit, VisitWorkflow $workflow)
    {
        $data = $request->validate(['symptoms' => ['nullable', 'string', 'max:5000'], 'findings' => ['nullable', 'string', 'max:5000'],
            'diagnosis' => ['required_if:action,sign', 'nullable', 'string', 'max:5000'], 'conclusion' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'], 'action' => ['required', 'in:draft,sign'], 'version' => ['required', 'integer', 'min:0'], 'amendment_reason' => ['nullable', 'string', 'max:255']]);
        $workflow->record($visit, $data);

        return back()->with('success', 'Đã lưu hồ sơ.');
    }

    public function prescription(Request $request, Visit $visit, VisitWorkflow $workflow)
    {
        $data = $request->validate(['bill_at_clinic' => ['nullable', 'boolean'], 'items' => ['required', 'array', 'min:1', 'max:50'], 'items.*.medicine_id' => ['required', 'exists:medicines,id'],
            'items.*.dosage' => ['required', 'string', 'max:255'], 'items.*.route' => ['required', 'string', 'max:100'],
            'items.*.frequency' => ['required', 'string', 'max:100'], 'items.*.duration_days' => ['required', 'integer', 'min:1', 'max:365'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:10000'], 'items.*.instructions' => ['required', 'string', 'max:255']]);
        $workflow->prescription($visit, $data['items'], $request->boolean('bill_at_clinic'));

        return back()->with('success', 'Đã lưu đơn nháp, chưa thay đổi tồn kho.');
    }

    public function sign(Prescription $prescription, VisitWorkflow $workflow)
    {
        $workflow->signPrescription($prescription);

        return back()->with('success', 'Đã ký đơn thuốc.');
    }

    public function order(Request $request, Visit $visit, VisitWorkflow $workflow)
    {
        $data = $request->validate(['service_ids' => ['required', 'array', 'min:1', 'max:50'], 'service_ids.*' => ['required', 'distinct', 'exists:services,id']]);
        $workflow->order($visit, $data['service_ids']);

        return back()->with('success', 'Đã tạo chỉ định dịch vụ.');
    }
}
