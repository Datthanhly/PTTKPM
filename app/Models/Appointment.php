<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    public const PENDING = 'PENDING';
    public const CONFIRMED = 'CONFIRMED';
    public const CHECKED_IN = 'CHECKED_IN';
    public const IN_PROGRESS = 'IN_PROGRESS';
    public const COMPLETED = 'COMPLETED';
    public const CANCELLED = 'CANCELLED';
    public const NO_SHOW = 'NO_SHOW';
    public const ACTIVE_STATUSES = [self::PENDING, self::CONFIRMED, self::CHECKED_IN, self::IN_PROGRESS];

    protected $fillable = ['appointment_code', 'patient_id', 'doctor_id', 'doctor_schedule_id', 'created_by', 'appointment_date', 'start_time', 'end_time', 'status', 'reason', 'patient_note', 'cancel_reason', 'checked_in_at', 'cancelled_at'];
    protected $casts = ['appointment_date' => 'date', 'checked_in_at' => 'datetime', 'cancelled_at' => 'datetime'];
    public function patient() { return $this->belongsTo(Patient::class); }
    public function doctor() { return $this->belongsTo(Doctor::class); }
    public function schedule() { return $this->belongsTo(DoctorSchedule::class, 'doctor_schedule_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function medicalRecord() { return $this->hasOne(MedicalRecord::class); }
    public function invoice() { return $this->hasOne(Invoice::class); }
}
