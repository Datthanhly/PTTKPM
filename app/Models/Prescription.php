<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Prescription extends Model
{
    protected $attributes = ['status' => 'DRAFT'];

    protected $guarded = ['id'];

    protected $casts = ['signed_at' => 'datetime'];

    public function visit()
    {
        return $this->belongsTo(Visit::class);
    }

    public function items()
    {
        return $this->hasMany(PrescriptionItem::class);
    }
}
