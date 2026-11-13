<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class ShiftTransferRequest extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['work_date' => 'date', 'responded_at' => 'datetime'];

    public function schedule()
    {
        return $this->belongsTo(DoctorSchedule::class, 'doctor_schedule_id');
    }

    public function sender()
    {
        return $this->belongsTo(Doctor::class, 'sender_doctor_id');
    }

    public function receiver()
    {
        return $this->belongsTo(Doctor::class, 'receiver_doctor_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function canRespond(): bool
    {
        return $this->status === 'PENDING' && Carbon::parse($this->work_date->format('Y-m-d').' '.$this->start_time)->isFuture();
    }
}
