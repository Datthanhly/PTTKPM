<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiagnosticResult extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['verified_at' => 'datetime'];

    public function item()
    {
        return $this->belongsTo(ServiceOrderItem::class, 'service_order_item_id');
    }
}
