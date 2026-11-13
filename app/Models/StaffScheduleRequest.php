<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffScheduleRequest extends Model
{
    public const ACTIVE = ['WAITING', 'ARRANGED', 'CONFIRMED'];

    protected $guarded = ['id'];

    protected $attributes = ['status' => 'WAITING'];

    protected $casts = ['work_date' => 'date', 'processed_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function shift()
    {
        return $this->hasOne(StaffWorkShift::class, 'request_id');
    }

    public function label(): string
    {
        return ['WAITING' => 'WAITING · Chờ xếp lịch', 'ARRANGED' => 'Đã xếp dự kiến', 'CONFIRMED' => 'Đã chốt', 'REJECTED' => 'Đã từ chối', 'CANCELLED' => 'Đã rút đăng ký'][$this->status] ?? $this->status;
    }
}
