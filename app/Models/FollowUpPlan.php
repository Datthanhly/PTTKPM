<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FollowUpPlan extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['suggested_from' => 'date', 'suggested_to' => 'date'];

    public function visit()
    {
        return $this->belongsTo(Visit::class);
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }
}
