<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Patient;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Builder;

class ClinicAccess
{
    public static function branches()
    {
        $user = auth()->user();

        return ($user->hasRole('admin') || $user->hasRole('patient') ? Branch::query() : $user->branches())->where('is_active', true)->orderBy('name')->get();
    }

    public static function scope(Builder $query, string $column = 'branch_id'): Builder
    {
        if (! auth()->user()->hasRole('admin')) {
            $query->whereIn($column, auth()->user()->branches()->select('branches.id')->where('is_active', true));
        }
        if (request()->filled('branch_id')) {
            $query->where($column, request()->integer('branch_id'));
        }

        return $query;
    }

    public static function require(string $permission, int $branchId): void
    {
        abort_unless(auth()->user()->hasPermission($permission, $branchId), 403, 'Không có quyền nghiệp vụ tại chi nhánh này.');
    }

    public static function treat(Visit $visit, string $permission = 'VISIT_TREAT'): void
    {
        self::require($permission, $visit->branch_id);
        abort_unless(auth()->user()->doctor?->id === $visit->doctor_id, 403, 'Lượt khám chưa được phân công cho bạn.');
    }

    public static function patients(): Builder
    {
        $ids = auth()->user()->branches()->select('branches.id');

        return Patient::query()->where(function ($q) use ($ids) {
            $q->whereIn('registered_branch_id', $ids)->orWhereHas('appointments', fn ($a) => $a->whereIn('branch_id', $ids))->orWhereHas('visits', fn ($v) => $v->whereIn('branch_id', $ids));
        });
    }
}
