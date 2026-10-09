<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = ['user_id', 'action', 'subject_type', 'subject_id', 'old_values', 'new_values', 'ip_address'];
    protected $casts = ['old_values' => 'array', 'new_values' => 'array'];

    public static function record(string $action, Model $subject, array $old = [], array $new = []): void
    {
        static::create([
            'user_id' => auth()->id(), 'action' => $action,
            'subject_type' => $subject::class, 'subject_id' => $subject->getKey(),
            'old_values' => $old ?: null, 'new_values' => $new ?: null,
            'ip_address' => request()?->ip(),
        ]);
    }
}
