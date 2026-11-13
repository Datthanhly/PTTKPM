<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Visit extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['checked_in_at' => 'datetime', 'started_at' => 'datetime', 'completed_at' => 'datetime'];

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function medicalRecord()
    {
        return $this->hasOne(MedicalRecord::class);
    }

    public function prescriptions()
    {
        return $this->hasMany(Prescription::class);
    }

    public function serviceOrders()
    {
        return $this->hasMany(ServiceOrder::class);
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class);
    }

    public function followUps()
    {
        return $this->hasMany(FollowUpPlan::class);
    }

    public function measurements()
    {
        return $this->hasMany(DeviceMeasurement::class);
    }
}
