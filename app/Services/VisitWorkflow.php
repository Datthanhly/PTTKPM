<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Doctor;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;

class VisitWorkflow
{
    public function checkIn(Appointment $appointment): Visit
    {
        ClinicAccess::require('VISIT_CHECK_IN', $appointment->branch_id);

        return DB::transaction(function () use ($appointment) {
            app(AppointmentBookingService::class)->lockDoctor($appointment->doctor_id);
            $a = Appointment::lockForUpdate()->findOrFail($appointment->id);
            ClinicAccess::require('VISIT_CHECK_IN', $a->branch_id);
            abort_if($a->doctor_id !== $appointment->doctor_id, 409, 'Bác sĩ phụ trách đã thay đổi. Vui lòng tải lại lịch trước khi tiếp nhận.');
            abort_unless($a->status === 'CONFIRMED' && ! $a->visit, 409, 'Lịch phải được xác nhận và chưa check-in.');
            abort_unless($a->appointment_date->isToday(), 422, 'Chỉ check-in lịch của ngày hôm nay.');
            $visit = $this->create($a->patient_id, $a->doctor_id, $a->branch_id, $a->reason, $a->id);
            $a->update(['status' => 'CHECKED_IN', 'checked_in_at' => now()]);
            app(NotificationService::class)->cancelReminders($a->id);

            return $visit;
        }, 5);
    }

    public function walkIn(Patient $patient, Doctor $doctor, int $branchId, string $reason): Visit
    {
        ClinicAccess::require('VISIT_CHECK_IN', $branchId);
        abort_unless($patient->is_active && $doctor->is_active && $doctor->user->is_active && $doctor->user->status === 'ACTIVE'
            && $doctor->user->branches()->where('branches.id', $branchId)->exists(), 422, 'Bệnh nhân/bác sĩ không hoạt động tại chi nhánh.');

        return DB::transaction(function () use ($patient, $doctor, $branchId, $reason) {
            app(AppointmentBookingService::class)->lockDoctor($doctor->id);

            return $this->create($patient->id, $doctor->id, $branchId, $reason);
        });
    }

    private function create(int $patient, int $doctor, int $branch, string $reason, ?int $appointment = null): Visit
    {
        $v = Visit::create(['visit_code' => 'LK'.strtoupper(bin2hex(random_bytes(8))), 'appointment_id' => $appointment,
            'patient_id' => $patient, 'doctor_id' => $doctor, 'branch_id' => $branch, 'reason' => $reason, 'status' => 'WAITING', 'checked_in_at' => now()]);
        AuditLog::record('visit.checked_in', $v);

        return $v;
    }

    public function transition(Visit $visit, string $status): void
    {
        ClinicAccess::treat($visit);
        DB::transaction(function () use ($visit, $status) {
            $v = Visit::lockForUpdate()->findOrFail($visit->id);
            $map = ['WAITING' => ['IN_PROGRESS', 'CANCELLED'], 'IN_PROGRESS' => ['WAITING_RESULT', 'COMPLETED', 'CANCELLED'], 'WAITING_RESULT' => ['IN_PROGRESS', 'COMPLETED'], 'COMPLETED' => ['CLOSED']];
            abort_unless(in_array($status, $map[$v->status] ?? []), 409, 'Chuyển trạng thái lượt khám không hợp lệ.');
            if ($status === 'COMPLETED') {
                abort_unless($v->medicalRecord?->status === 'SIGNED', 422, 'Cần ký hồ sơ trước khi hoàn tất.');
                abort_if($v->prescriptions()->where('status', 'DRAFT')->exists(), 422, 'Còn đơn thuốc chưa ký.');
                abort_if($v->serviceOrders()->whereNotIn('status', ['COMPLETED', 'CANCELLED'])->exists(), 422, 'Còn dịch vụ chưa hoàn tất/duyệt kết quả.');
            }
            $values = ['status' => $status];
            if ($status === 'IN_PROGRESS' && ! $v->started_at) {
                $values['started_at'] = now();
            }
            if ($status === 'COMPLETED') {
                $values['completed_at'] = now();
            }
            $v->update($values);
            if ($status === 'COMPLETED' && $v->appointment) {
                $v->appointment->update(['status' => 'COMPLETED']);
            }
            AuditLog::record('visit.status_changed', $v, [], ['status' => $status]);
        }, 5);
    }

    public function record(Visit $visit, array $data): MedicalRecord
    {
        ClinicAccess::treat($visit, ($data['action'] ?? '') === 'sign' ? 'RECORD_SIGN' : 'VISIT_TREAT');

        return DB::transaction(function () use ($visit, $data) {
            $v = Visit::lockForUpdate()->findOrFail($visit->id);
            $record = $v->medicalRecord;
            $amendClosed = in_array($v->status, ['COMPLETED', 'CLOSED']) && $record?->status === 'SIGNED' && ! empty($data['amendment_reason']) && ($data['action'] ?? '') === 'sign';
            abort_unless(in_array($v->status, ['IN_PROGRESS', 'WAITING_RESULT']) || $amendClosed, 409, 'Lượt đã hoàn tất chỉ cho phép điều chỉnh hồ sơ đã ký với lý do.');
            abort_if($record && $record->version !== (int) $data['version'], 409, 'Hồ sơ đã thay đổi. Tải lại trước khi lưu.');
            if ($record?->status === 'SIGNED') {
                abort_unless(! empty($data['amendment_reason']), 422, 'Hồ sơ đã ký: phải nhập lý do điều chỉnh.');
                DB::table('medical_record_revisions')->insert(['medical_record_id' => $record->id, 'version' => $record->version,
                    'snapshot' => json_encode($record->only(['symptoms', 'findings', 'diagnosis', 'conclusion', 'notes', 'signed_at', 'signed_by'])),
                    'reason' => $data['amendment_reason'], 'updated_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
            }
            $sign = ($data['action'] ?? 'draft') === 'sign';
            $record = MedicalRecord::updateOrCreate(['visit_id' => $v->id], [
                'appointment_id' => $v->appointment_id, 'doctor_id' => $v->doctor_id, 'symptoms' => $data['symptoms'] ?? null,
                'findings' => $data['findings'] ?? null, 'diagnosis' => $data['diagnosis'] ?? '', 'conclusion' => $data['conclusion'] ?? null,
                'notes' => $data['notes'] ?? null, 'status' => $sign ? 'SIGNED' : 'DRAFT', 'version' => ($record?->version ?? 0) + 1,
                'signed_at' => $sign ? now() : null, 'signed_by' => $sign ? auth()->id() : null, 'published_at' => $sign ? now() : null,
            ]);
            AuditLog::record($sign ? 'record.signed' : 'record.draft_saved', $v, [], ['version' => $record->version]);

            return $record;
        }, 5);
    }

    public function prescription(Visit $visit, array $items, bool $billAtClinic = false): Prescription
    {
        ClinicAccess::treat($visit);

        return DB::transaction(function () use ($visit, $items, $billAtClinic) {
            $v = Visit::lockForUpdate()->findOrFail($visit->id);
            abort_unless(in_array($v->status, ['IN_PROGRESS', 'WAITING_RESULT']), 409, 'Lượt khám đã đóng.');
            $p = $v->prescriptions()->create(['doctor_id' => $v->doctor_id, 'bill_at_clinic' => $billAtClinic]);
            foreach ($items as $item) {
                abort_unless(\App\Models\Medicine::whereKey($item['medicine_id'])->where('is_active', true)->exists(), 422, 'Thuốc đã vô hiệu hóa.');
                $p->items()->create($item);
            }
            AuditLog::record('prescription.draft_created', $v, [], ['prescription_id' => $p->id]);

            return $p;
        });
    }

    public function signPrescription(Prescription $p): void
    {
        ClinicAccess::treat($p->visit, 'PRESCRIPTION_SIGN');
        DB::transaction(function () use ($p) {
            $p = Prescription::lockForUpdate()->findOrFail($p->id);
            abort_unless($p->status === 'DRAFT' && $p->items()->exists() && in_array($p->visit->status, ['IN_PROGRESS', 'WAITING_RESULT']), 409, 'Đơn không đủ điều kiện ký.');
            $p->update(['status' => 'SIGNED', 'signed_at' => now(), 'signed_by' => auth()->id()]);
            AuditLog::record('prescription.signed', $p->visit, [], ['prescription_id' => $p->id]);
        });
    }

    public function order(Visit $visit, array $ids): ServiceOrder
    {
        ClinicAccess::treat($visit, 'SERVICE_ORDER_CREATE');

        return DB::transaction(function () use ($visit, $ids) {
            $v = Visit::lockForUpdate()->findOrFail($visit->id);
            abort_unless(in_array($v->status, ['IN_PROGRESS', 'WAITING_RESULT']) && ! $v->invoice, 409, 'Lượt khám/hóa đơn đã chốt.');
            $o = $v->serviceOrders()->create(['doctor_id' => $v->doctor_id]);
            foreach (array_unique($ids) as $id) {
                abort_unless(Service::whereKey($id)->where('is_active', true)->exists(), 422, 'Dịch vụ không hoạt động.');
                $o->items()->create(['service_id' => $id, 'external_order_code' => 'ORDER-'.strtoupper(bin2hex(random_bytes(10)))]);
            }
            AuditLog::record('service_order.created', $v, [], ['order_id' => $o->id]);

            return $o;
        });
    }
}
