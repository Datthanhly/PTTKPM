<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BillingService
{
    public function issue(Visit $visit, array $adjustment = []): Invoice
    {
        ClinicAccess::require('INVOICE_ISSUE', $visit->branch_id);

        return DB::transaction(function () use ($visit, $adjustment) {
            $v = Visit::lockForUpdate()->findOrFail($visit->id);
            abort_unless(in_array($v->status, ['COMPLETED', 'CLOSED']), 422, 'Lượt khám chưa hoàn tất.');
            abort_if($v->invoice, 409, 'Lượt khám đã có hóa đơn chính.');
            $invoice = Invoice::create(['visit_id' => $v->id, 'appointment_id' => $v->appointment_id, 'branch_id' => $v->branch_id,
                'invoice_code' => 'HD'.strtoupper(bin2hex(random_bytes(8))), 'issued_by' => auth()->id(), 'issued_at' => now(), 'status' => 'ISSUED']);
            $fee = Money::cents(config('clinic.consultation_fee'));
            $invoice->items()->create(['source_type' => 'CONSULTATION', 'source_id' => $v->id, 'description' => 'Phí khám', 'quantity' => 1, 'unit_price' => Money::decimal($fee), 'amount' => Money::decimal($fee)]);
            $total = $fee;
            foreach ($v->serviceOrders()->where('status', 'COMPLETED')->with('items.service')->get() as $order) {
                foreach ($order->items as $item) {
                    if ($item->status === 'CANCELLED') {
                        continue;
                    }
                    $price = Money::cents($item->service->price);
                    $invoice->items()->create(['source_type' => 'SERVICE', 'source_id' => $item->id, 'service_id' => $item->service_id,
                        'description' => $item->service->name, 'quantity' => 1, 'unit_price' => Money::decimal($price), 'amount' => Money::decimal($price)]);
                    $total += $price;
                }
            }
            // Demo policy: signed items marked for sale at the clinic are billed
            // once. Dispensing may happen after payment and never adds fees again.
            foreach (DB::table('prescription_items as pi')->join('prescriptions as p', 'p.id', '=', 'pi.prescription_id')
                ->join('medicines as m', 'm.id', '=', 'pi.medicine_id')->where('p.visit_id', $v->id)->where('p.status', 'SIGNED')->where('p.bill_at_clinic', true)
                ->select('pi.id', 'pi.quantity', 'm.name', 'm.price')->get() as $line) {
                $price = Money::cents($line->price);
                $amount = $price * $line->quantity;
                $invoice->items()->create(['source_type' => 'PRESCRIPTION_SALE', 'source_id' => $line->id, 'description' => $line->name,
                    'quantity' => $line->quantity, 'unit_price' => Money::decimal($price), 'amount' => Money::decimal($amount)]);
                $total += $amount;
            }
            // Preserve service lines saved by the previous application version.
            if ($v->medicalRecord) {
                foreach ($v->medicalRecord->visitServices()->with('service')->get() as $line) {
                    $amount = Money::cents($line->unit_price) * $line->quantity;
                    $invoice->items()->create(['source_type' => 'LEGACY_SERVICE', 'source_id' => $line->id, 'service_id' => $line->service_id,
                        'description' => $line->service->name, 'quantity' => $line->quantity, 'unit_price' => $line->unit_price, 'amount' => Money::decimal($amount)]);
                    $total += $amount;
                }
            }
            $discount = Money::cents($adjustment['discount'] ?? 0);
            abort_if($discount > $total || ($discount > 0 && empty($adjustment['discount_reason'])), 422, 'Giảm giá phải có lý do và không vượt tạm tính.');
            $invoice->update(['subtotal' => Money::decimal($total), 'discount' => Money::decimal($discount), 'total' => Money::decimal($total - $discount)]);
            AuditLog::record('invoice.issued', $invoice, [], ['discount' => Money::decimal($discount), 'discount_reason' => $adjustment['discount_reason'] ?? null]);

            return $invoice;
        }, 5);
    }

    public function pay(Invoice $invoice, array $data): Payment
    {
        ClinicAccess::require('PAYMENT_COLLECT', $invoice->branch_id);

        return DB::transaction(function () use ($invoice, $data) {
            DB::table('invoices')->where('id', $invoice->id)->update(['updated_at' => DB::raw('updated_at')]);
            $invoice = Invoice::lockForUpdate()->findOrFail($invoice->id);
            if ($existing = Payment::where('idempotency_key', $data['idempotency_key'])->first()) {
                abort_unless($existing->invoice_id === $invoice->id && Money::cents($existing->amount) === Money::cents($data['amount'])
                    && $existing->method === $data['method'] && $existing->reference === ($data['reference'] ?? null), 409, 'Khóa thanh toán đã dùng cho nội dung khác.');

                return $existing;
            }
            abort_unless($invoice->status === 'ISSUED', 422, 'Hóa đơn chưa phát hành hoặc đã hủy.');
            $amount = Money::cents($data['amount']);
            if ($amount <= 0 || $amount > $invoice->outstanding_cents) {
                throw ValidationException::withMessages(['amount' => 'Số tiền phải lớn hơn 0 và không vượt số còn phải thu.']);
            }
            abort_unless(in_array($data['method'], ['CASH', 'TRANSFER']), 422);
            abort_if($data['method'] === 'TRANSFER' && empty($data['confirmed']), 422, 'Cần xác nhận đã nhận chuyển khoản.');
            $p = $invoice->payments()->create(['amount' => Money::decimal($amount), 'method' => $data['method'], 'reference' => $data['reference'] ?? null,
                'idempotency_key' => $data['idempotency_key'], 'status' => 'SUCCEEDED', 'received_by' => auth()->id(), 'paid_at' => now()]);
            if ($invoice->fresh()->outstanding_cents === 0) {
                $invoice->update(['paid_at' => now()]);
            }
            AuditLog::record('payment.received', $invoice, [], ['payment_id' => $p->id, 'amount' => $p->amount]);

            return $p;
        }, 5);
    }

    public function refund(Payment $payment, array $data): void
    {
        ClinicAccess::require('PAYMENT_COLLECT', $payment->invoice->branch_id);
        DB::transaction(function () use ($payment, $data) {
            $invoice = Invoice::lockForUpdate()->findOrFail($payment->invoice_id);
            if ($r = DB::table('refunds')->where('idempotency_key', $data['idempotency_key'])->first()) {
                abort_unless($r->payment_id === $payment->id && Money::cents($r->amount) === Money::cents($data['amount']), 409);

                return;
            }
            $refunded = DB::table('refunds')->where('payment_id', $payment->id)->get()->sum(fn ($r) => Money::cents($r->amount));
            $amount = Money::cents($data['amount']);
            abort_unless($payment->status === 'SUCCEEDED' && $amount > 0 && $amount <= Money::cents($payment->amount) - $refunded, 422, 'Số tiền hoàn không hợp lệ.');
            DB::table('refunds')->insert(['payment_id' => $payment->id, 'amount' => Money::decimal($amount), 'reason' => $data['reason'],
                'idempotency_key' => $data['idempotency_key'], 'approved_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
            $invoice->update(['paid_at' => null]);
            AuditLog::record('payment.refunded', $invoice, [], ['payment_id' => $payment->id, 'amount' => Money::decimal($amount), 'reason' => $data['reason']]);
        }, 5);
    }
}
