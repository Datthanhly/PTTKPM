<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceMeasurement extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['measured_at' => 'datetime'];

    public function visit()
    {
        return $this->belongsTo(Visit::class);
    }
}
