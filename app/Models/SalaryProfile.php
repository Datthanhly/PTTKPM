<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalaryProfile extends Model
{
    public const ROLES = ['doctor', 'receptionist', 'cashier'];

    protected $guarded = ['id'];

    protected $casts = ['unit_rate' => 'integer', 'version' => 'integer'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
