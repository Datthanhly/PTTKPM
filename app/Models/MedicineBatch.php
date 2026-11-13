<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MedicineBatch extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['expiry_date' => 'date'];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function medicine()
    {
        return $this->belongsTo(Medicine::class);
    }
}
