<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DispensationItem extends Model
{
    protected $guarded = ['id'];

    public function batch()
    {
        return $this->belongsTo(MedicineBatch::class, 'batch_id');
    }
}
