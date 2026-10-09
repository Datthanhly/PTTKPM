<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VisitService extends Model
{
    protected $fillable = ['medical_record_id', 'service_id', 'quantity', 'unit_price', 'note'];
    protected $casts = ['unit_price' => 'decimal:2'];
    public function medicalRecord() { return $this->belongsTo(MedicalRecord::class); }
    public function service() { return $this->belongsTo(Service::class); }
}
