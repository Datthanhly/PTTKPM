<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceOrderItem extends Model
{
    protected $guarded = ['id'];

    public function order()
    {
        return $this->belongsTo(ServiceOrder::class, 'service_order_id');
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function results()
    {
        return $this->hasMany(DiagnosticResult::class);
    }
}
