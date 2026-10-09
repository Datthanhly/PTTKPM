<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BillingController extends Controller
{
    public function create(Appointment $appointment)
    {
        abort_unless($appointment->status === Appointment::COMPLETED, 422, 'Ca khám chưa hoàn tất.');
        $appointment->load(['patient', 'doctor.user', 'medicalRecord.visitServices.service', 'invoice.items', 'invoice.payments']);
        return view('staff.invoice', compact('appointment'));
    }

    public function store(Appointment $appointment)
    {
        abort_unless($appointment->status === Appointment::COMPLETED && $appointment->medicalRecord, 422);
        $invoice = DB::transaction(function () use ($appointment) {
            $invoice = Invoice::firstOrCreate(['appointment_id' => $appointment->id], [
                'invoice_code' => 'HD'.now()->format('ymdHis').$appointment->id,
                'issued_by' => auth()->id(), 'issued_at' => now(), 'status' => 'UNPAID',
            ]);
            if ($invoice->items()->doesntExist()) {
                foreach ($appointment->medicalRecord->visitServices()->with('service')->get() as $line) {
                    $invoice->items()->create([
                        'service_id' => $line->service_id, 'description' => $line->service->name,
                        'quantity' => $line->quantity, 'unit_price' => $line->unit_price,
                        'amount' => $line->quantity * $line->unit_price,
                    ]);
                }
                $subtotal = $invoice->items()->sum('amount');
                $invoice->update(['subtotal' => $subtotal, 'total' => $subtotal]);
            }
            return $invoice;
        });
        AuditLog::record('invoice.issued', $invoice);
        return redirect()->route('billing.create', $appointment)->with('success', 'Đã lập hóa đơn.');
    }

    public function pay(Request $request, Invoice $invoice)
    {
        abort_if($invoice->status === 'PAID', 422, 'Hóa đơn đã thanh toán.');
        $paid = (float) $invoice->payments()->sum('amount');
        $remaining = max(0, (float) $invoice->total - $paid);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:'.$remaining],
            'method' => ['required', 'in:CASH,TRANSFER,CARD'], 'reference' => ['nullable', 'string', 'max:80'],
        ]);
        $payment = $invoice->payments()->create([...$data, 'received_by' => auth()->id(), 'paid_at' => now()]);
        if ((float) $invoice->payments()->sum('amount') >= (float) $invoice->total) $invoice->update(['status' => 'PAID', 'paid_at' => now()]);
        AuditLog::record('payment.received', $payment);
        return back()->with('success', 'Đã ghi nhận thanh toán.');
    }
}
