<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dispensation extends Model
{
    protected $guarded = ['id'];

    public function prescription()
    {
        return $this->belongsTo(Prescription::class);
    }

    public function items()
    {
        return $this->hasMany(DispensationItem::class);
    }
}
