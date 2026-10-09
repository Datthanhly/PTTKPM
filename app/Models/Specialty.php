<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Specialty extends Model
{
    protected $fillable = ['code', 'name', 'description', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];
    public function doctors() { return $this->hasMany(Doctor::class); }
}
