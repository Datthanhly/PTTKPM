<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = ['visit_id', 'branch_id', 'invoice_code', 'appointment_id', 'issued_by', 'subtotal', 'discount', 'total', 'status', 'issued_at', 'paid_at'];

    protected $casts = ['subtotal' => 'decimal:2', 'discount' => 'decimal:2', 'total' => 'decimal:2', 'issued_at' => 'datetime', 'paid_at' => 'datetime'];

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function issuer()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function visit()
    {
        return $this->belongsTo(Visit::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function getReceivedCentsAttribute(): int
    {
        $received = $this->payments()->where('status', 'SUCCEEDED')->get()->sum(fn ($p) => \App\Services\Money::cents($p->amount));
        $refunds = \Illuminate\Support\Facades\DB::table('refunds')->whereIn('payment_id', $this->payments()->select('id'))->get()->sum(fn ($r) => \App\Services\Money::cents($r->amount));

        return $received - $refunds;
    }

    public function getOutstandingCentsAttribute(): int
    {
        return max(0, \App\Services\Money::cents($this->total) - $this->received_cents);
    }

    public function getPaymentStatusAttribute(): string
    {
        return $this->outstanding_cents === 0 ? 'PAID' : ($this->received_cents > 0 ? 'PARTIAL' : 'UNPAID');
    }
}
