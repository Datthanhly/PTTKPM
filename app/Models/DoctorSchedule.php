<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DoctorSchedule extends Model
{
    protected $fillable = ['doctor_id', 'work_date', 'start_time', 'end_time', 'slot_minutes', 'is_open'];
    protected $casts = ['work_date' => 'date', 'is_open' => 'boolean'];
    public function doctor() { return $this->belongsTo(Doctor::class); }
    public function appointments() { return $this->hasMany(Appointment::class); }
}
