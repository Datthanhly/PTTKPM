<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = ['invoice_code', 'appointment_id', 'issued_by', 'subtotal', 'discount', 'total', 'status', 'issued_at', 'paid_at'];
    protected $casts = ['subtotal' => 'decimal:2', 'discount' => 'decimal:2', 'total' => 'decimal:2', 'issued_at' => 'datetime', 'paid_at' => 'datetime'];
    public function appointment() { return $this->belongsTo(Appointment::class); }
    public function items() { return $this->hasMany(InvoiceItem::class); }
    public function payments() { return $this->hasMany(Payment::class); }
    public function issuer() { return $this->belongsTo(User::class, 'issued_by'); }
}
