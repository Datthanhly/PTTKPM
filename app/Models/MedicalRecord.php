<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MedicalRecord extends Model
{
    protected $fillable = ['appointment_id', 'doctor_id', 'symptoms', 'diagnosis', 'conclusion', 'prescription', 'notes', 'published_at'];
    protected $casts = ['published_at' => 'datetime'];
    public function appointment() { return $this->belongsTo(Appointment::class); }
    public function doctor() { return $this->belongsTo(Doctor::class); }
    public function visitServices() { return $this->hasMany(VisitService::class); }
}
