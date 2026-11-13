<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Doctor;
use App\Models\Patient;
use App\Services\ClinicAccess;
use App\Services\VisitWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PatientController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()->hasRole('receptionist'), 403);
        $patients = ClinicAccess::patients()->when($request->filled('q'), fn ($q) => $q->where(fn ($p) => $p->where('patient_code', 'like', '%'.$request->q.'%')->orWhere('full_name', 'like', '%'.$request->q.'%')->orWhere('phone', 'like', '%'.$request->q.'%')))->orderBy('full_name')->paginate(20)->withQueryString();

        return view('patients.index', ['patients' => $patients, 'branches' => ClinicAccess::branches(), 'doctors' => Doctor::with('user')->where('is_active', true)->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['registered_branch_id' => ['required', 'exists:branches,id'], 'full_name' => ['required', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'], 'gender' => ['nullable', 'in:Nam,Nữ,Khác'], 'phone' => ['nullable', 'string', 'max:20'], 'email' => ['nullable', 'email', 'max:255'], 'address' => ['nullable', 'string', 'max:255']]);
        ClinicAccess::require('PATIENT_ADMIN_WRITE', (int) $data['registered_branch_id']);
        $duplicate = ClinicAccess::patients()->when(! empty($data['phone']), fn ($q) => $q->where('phone', $data['phone']), fn ($q) => $q->where('full_name', $data['full_name']))->exists();
        $p = Patient::create([...$data, 'patient_code' => 'BN'.strtoupper(bin2hex(random_bytes(7)))]);
        AuditLog::record('patient.created', $p);

        return back()->with('success', 'Đã tạo '.$p->patient_code.($duplicate ? '. Có hồ sơ cùng tên/điện thoại; hãy kiểm tra trùng, hệ thống không tự gộp.' : '.'));
    }

    public function update(Request $request, Patient $patient)
    {
        abort_unless(ClinicAccess::patients()->whereKey($patient->id)->exists(), 403);
        $data = $request->validate(['full_name' => ['required', 'string', 'max:255'], 'phone' => ['nullable', 'string', 'max:20'], 'address' => ['nullable', 'string', 'max:255'], 'is_active' => ['required', 'boolean']]);
        $old = $patient->only(['full_name', 'phone', 'address', 'is_active']);
        $patient->update($data);
        AuditLog::record('patient.administrative_updated', $patient, [], ['changed_fields' => array_keys(array_diff_assoc($data, $old))]);

        return back()->with('success', 'Đã cập nhật hồ sơ hành chính.');
    }

    public function walkIn(Request $request, VisitWorkflow $workflow)
    {
        $data = $request->validate(['patient_id' => ['required', 'exists:patients,id'], 'doctor_id' => ['required', 'exists:doctors,id'], 'branch_id' => ['required', 'exists:branches,id'], 'reason' => ['required', 'string', 'max:2000']]);
        $patient = ClinicAccess::patients()->findOrFail($data['patient_id']);
        $workflow->walkIn($patient, Doctor::findOrFail($data['doctor_id']), (int) $data['branch_id'], $data['reason']);

        return redirect()->route('visits.index')->with('success', 'Đã tiếp nhận khách vãng lai, không tạo lịch hẹn giả.');
    }

    public function profile()
    {
        $patient = auth()->user()->patient;
        abort_unless($patient, 403);

        return view('patients.profile', compact('patient'));
    }

    public function saveProfile(Request $request)
    {
        $p = auth()->user()->patient;
        abort_unless($p, 403);
        $data = $request->validate(['full_name' => ['sometimes', 'required', 'string', 'max:255'], 'phone' => ['sometimes', 'required', 'string', 'max:20', Rule::unique('users')->ignore(auth()->id())],
            'birth_date' => ['sometimes', 'required', 'date', 'before_or_equal:today'], 'gender' => ['sometimes', 'required', 'in:Nam,Nữ,Khác'],
            'address' => ['nullable', 'string', 'max:255'], 'allergies' => ['nullable', 'string', 'max:3000'],
            'medical_history' => ['nullable', 'string', 'max:3000'], 'emergency_contact' => ['nullable', 'string', 'max:255'], 'email_enabled' => ['nullable', 'boolean'], 'auto_booking' => ['nullable', 'boolean']]);
        $data['email_enabled'] = $request->boolean('email_enabled');
        if ($request->boolean('auto_booking')) {
            $data['auto_booking_consented_at'] = $p->auto_booking_consented_at ?? now();
            $data['auto_booking_expires_at'] = now()->addMonths(6);
        } else {
            $data['auto_booking_consented_at'] = null;
            $data['auto_booking_expires_at'] = null;
        }
        unset($data['auto_booking']);
        DB::transaction(function () use ($p, $data) {
            $p->update($data);
            $userData = [];
            if (isset($data['full_name'])) {
                $userData['name'] = $data['full_name'];
            } if (isset($data['phone'])) {
                $userData['phone'] = $data['phone'];
            }
            if ($userData) {
                $p->user->update($userData);
            }
        });
        AuditLog::record('patient.preferences_updated', $p, [], ['email_enabled' => $data['email_enabled'], 'auto_booking' => $request->boolean('auto_booking')]);

        return back()->with('success', 'Đã cập nhật hồ sơ và tùy chọn.');
    }

    public function history()
    {
        $patient = auth()->user()->patient;
        abort_unless($patient, 403);
        $visits = $patient->visits()->whereHas('medicalRecord', fn ($q) => $q->where('status', 'SIGNED')->whereNotNull('published_at'))
            ->with(['branch', 'doctor.user', 'medicalRecord', 'prescriptions' => fn ($q) => $q->where('status', 'SIGNED'), 'prescriptions.items.medicine',
                'serviceOrders.items.service', 'serviceOrders.items.results' => fn ($q) => $q->where('status', 'VERIFIED'), 'invoice'])->latest('checked_in_at')->paginate(10);

        return view('patients.history', compact('visits'));
    }
}
