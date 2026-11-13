<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollEntry extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'pay_month' => 'date', 'calculated_at' => 'datetime', 'paid_at' => 'datetime',
        'source_shifts' => 'array', 'unit_rate' => 'integer', 'base_amount' => 'integer',
        'allowance' => 'integer', 'bonus' => 'integer', 'deduction' => 'integer', 'net_amount' => 'integer',
        'worked_minutes' => 'integer', 'worked_shifts' => 'integer', 'version' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function paidBy()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function basisLabel(): string
    {
        return $this->pay_basis === 'HOUR' ? 'Theo giờ' : 'Theo ca';
    }

    public function statusLabel(): string
    {
        return $this->status === 'PAID' ? 'Đã thanh toán' : 'Chưa thanh toán';
    }
}
