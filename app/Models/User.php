<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $attributes = ['is_active' => true, 'status' => 'ACTIVE'];

    protected $fillable = ['role_id', 'name', 'email', 'phone', 'password', 'is_active', 'status'];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = ['email_verified_at' => 'datetime', 'password' => 'hashed', 'is_active' => 'boolean'];

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function patient()
    {
        return $this->hasOne(Patient::class);
    }

    public function doctor()
    {
        return $this->hasOne(Doctor::class);
    }

    public function branches()
    {
        return $this->belongsToMany(Branch::class);
    }

    public function salaryProfile()
    {
        return $this->hasOne(SalaryProfile::class);
    }

    public function canAccessBranch(int $branchId): bool
    {
        return $this->hasRole('admin') || $this->branches()->where('branches.id', $branchId)->where('is_active', true)->exists();
    }

    public function hasPermission(string $permission, ?int $branchId = null): bool
    {
        if (! $this->is_active || $this->status !== 'ACTIVE') {
            return false;
        }
        if ($branchId !== null && ! $this->canAccessBranch($branchId)) {
            return false;
        }
        if (in_array($permission, config('clinic.permissions.'.$this->role?->code, []), true)) {
            return true;
        }

        return \Illuminate\Support\Facades\DB::table('user_permissions')->where('user_id', $this->id)->where('permission_code', $permission)
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))->exists();
    }

    public function hasRole(string $role): bool
    {
        return $this->role?->code === $role;
    }

    public function hasAnyRole(array $roles): bool
    {
        return in_array($this->role?->code, $roles, true);
    }
}
