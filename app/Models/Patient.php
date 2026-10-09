<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    protected $fillable = ['user_id', 'patient_code', 'full_name', 'birth_date', 'gender', 'phone', 'email', 'address', 'blood_type', 'insurance_number', 'allergies'];
    protected $casts = ['birth_date' => 'date'];
    public function user() { return $this->belongsTo(User::class); }
    public function appointments() { return $this->hasMany(Appointment::class); }
}
