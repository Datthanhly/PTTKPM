<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    protected $attributes = ['is_active' => true];

    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean'];

    public function shortLabel(): string
    {
        return preg_match('/^(?:CN)?0*([1-9]\d*)$/i', $this->code, $match)
            ? 'Cơ sở '.(int) $match[1]
            : $this->name;
    }

    public function users()
    {
        return $this->belongsToMany(User::class);
    }
}
