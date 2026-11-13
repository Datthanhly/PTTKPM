<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationJob extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['scheduled_at' => 'datetime', 'sent_at' => 'datetime'];

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }
}
