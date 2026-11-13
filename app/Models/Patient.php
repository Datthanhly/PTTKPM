<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    protected $attributes = ['is_active' => true, 'email_enabled' => true];

    protected $fillable = ['registered_branch_id', 'medical_history', 'emergency_contact', 'is_active', 'email_enabled', 'auto_booking_consented_at', 'auto_booking_expires_at', 'user_id', 'patient_code', 'full_name', 'birth_date', 'gender', 'phone', 'email', 'address', 'blood_type', 'insurance_number', 'allergies'];

    protected $casts = ['birth_date' => 'date', 'is_active' => 'boolean', 'email_enabled' => 'boolean', 'auto_booking_consented_at' => 'datetime', 'auto_booking_expires_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function visits()
    {
        return $this->hasMany(Visit::class);
    }
}
