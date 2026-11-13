<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Medicine extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['price' => 'decimal:2', 'is_active' => 'boolean'];
}
