<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class StaffWorkShift extends Model
{
    public const ACTIVE = ['DRAFT', 'CONFIRMED'];

    protected $guarded = ['id'];

    protected $attributes = ['status' => 'DRAFT'];

    protected $casts = ['work_date' => 'date', 'published_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function request()
    {
        return $this->belongsTo(StaffScheduleRequest::class, 'request_id');
    }

    public function startsAt(): Carbon
    {
        return Carbon::parse($this->work_date->format('Y-m-d').' '.$this->start_time);
    }

    public function minutes(): int
    {
        return Carbon::parse($this->start_time)->diffInMinutes(Carbon::parse($this->end_time));
    }

    public function version(): string
    {
        return hash('sha256', json_encode([$this->id, $this->request_id, $this->user_id, $this->branch_id, $this->role_code,
            $this->work_date->format('Y-m-d'), substr($this->start_time, 0, 5), substr($this->end_time, 0, 5), $this->status, $this->note, $this->updated_at?->getTimestamp()]));
    }
}
