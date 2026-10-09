<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\MedicalRecord;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MedicalRecordController extends Controller
{
    public function edit(Appointment $appointment)
    {
        $this->authorizeDoctor($appointment);
        $appointment->load(['patient', 'medicalRecord.visitServices']);
        $services = Service::where('is_active', true)->orderBy('name')->get();
        return view('staff.medical-record', compact('appointment', 'services'));
    }

    public function update(Request $request, Appointment $appointment)
    {
        $this->authorizeDoctor($appointment);
        abort_unless(in_array($appointment->status, [Appointment::CHECKED_IN, Appointment::IN_PROGRESS], true), 422, 'Bệnh nhân chưa ở trạng thái đang khám.');
        $data = $request->validate([
            'symptoms' => ['nullable', 'string'], 'diagnosis' => ['required', 'string'],
            'conclusion' => ['nullable', 'string'], 'prescription' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'], 'service_ids' => ['array'], 'service_ids.*' => ['exists:services,id'],
        ]);

        $record = DB::transaction(function () use ($data, $appointment) {
            $record = MedicalRecord::updateOrCreate(['appointment_id' => $appointment->id], [
                'doctor_id' => $appointment->doctor_id, 'symptoms' => $data['symptoms'] ?? null,
                'diagnosis' => $data['diagnosis'], 'conclusion' => $data['conclusion'] ?? null,
                'prescription' => $data['prescription'] ?? null, 'notes' => $data['notes'] ?? null,
                'published_at' => now(),
            ]);
            $record->visitServices()->delete();
            foreach (Service::whereIn('id', $data['service_ids'] ?? [])->get() as $service) {
                $record->visitServices()->create(['service_id' => $service->id, 'quantity' => 1, 'unit_price' => $service->price]);
            }
            $appointment->update(['status' => Appointment::COMPLETED]);
            return $record;
        });
        AuditLog::record('medical_record.completed', $record);
        return redirect()->route('staff.appointments')->with('success', 'Đã lưu hồ sơ và hoàn tất ca khám.');
    }

    private function authorizeDoctor(Appointment $appointment): void
    {
        $user = auth()->user();
        abort_unless($user->hasRole('admin') || ($user->hasRole('doctor') && $appointment->doctor_id === $user->doctor?->id), 403);
    }
}
