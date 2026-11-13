<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Visit;
use App\Services\BillingService;
use App\Services\ClinicAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BillingController extends Controller
{
    public function create(Appointment $appointment)
    {
        abort_unless($appointment->visit, 422, 'Chưa có lượt khám.');

        return $this->show($appointment->visit);
    }

    public function show(Visit $visit)
    {
        ClinicAccess::require('INVOICE_ISSUE', $visit->branch_id);
        $visit->load(['patient', 'doctor.user', 'branch', 'invoice.items', 'invoice.payments']);

        return view('staff.invoice', compact('visit'));
    }

    public function store(Request $request, Appointment $appointment, BillingService $billing)
    {
        abort_unless($appointment->visit, 422);
        $billing->issue($appointment->visit, $this->adjustment($request));

        return back()->with('success', 'Đã phát hành hóa đơn.');
    }

    public function issue(Request $request, Visit $visit, BillingService $billing)
    {
        $billing->issue($visit, $this->adjustment($request));

        return back()->with('success', 'Đã phát hành hóa đơn.');
    }

    public function pay(Request $request, Invoice $invoice, BillingService $billing)
    {
        $request->merge(['idempotency_key' => $request->header('Idempotency-Key', $request->input('idempotency_key'))]);
        $data = $request->validate(['amount' => ['required', 'regex:/^\d{1,10}(\.\d{1,2})?$/'], 'method' => ['required', 'in:CASH,TRANSFER'],
            'reference' => ['nullable', 'string', 'max:80'], 'idempotency_key' => ['required', 'string', 'max:100'], 'confirmed' => ['nullable', 'boolean']]);
        $billing->pay($invoice, $data);

        return back()->with('success', 'Đã ghi nhận thanh toán.');
    }

    public function refund(Request $request, Payment $payment, BillingService $billing)
    {
        $data = $request->validate(['amount' => ['required', 'regex:/^\d{1,10}(\.\d{1,2})?$/'], 'reason' => ['required', 'string', 'max:255'], 'idempotency_key' => ['required', 'string', 'max:100']]);
        $billing->refund($payment, $data);

        return back()->with('success', 'Đã ghi nhận hoàn tiền.');
    }

    public function void(Request $request, Invoice $invoice)
    {
        ClinicAccess::require('INVOICE_ISSUE', $invoice->branch_id);
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        DB::transaction(function () use ($invoice, $data) {
            $i = Invoice::lockForUpdate()->findOrFail($invoice->id);
            abort_unless($i->status === 'ISSUED' && $i->received_cents === 0, 422, 'Chỉ hủy hóa đơn không còn tiền thu.');
            $i->update(['status' => 'VOID']);
            AuditLog::record('invoice.voided', $i, [], $data);
        });

        return back()->with('success', 'Đã hủy hóa đơn và giữ nguyên lịch sử.');
    }

    private function adjustment(Request $request): array
    {
        return $request->validate(['discount' => ['nullable', 'regex:/^\d{1,10}(\.\d{1,2})?$/'], 'discount_reason' => ['nullable', 'string', 'max:255']]);
    }
}
