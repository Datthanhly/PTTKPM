<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\DoctorSchedule;
use App\Models\PayrollEntry;
use App\Models\SalaryProfile;
use App\Models\StaffWorkShift;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayrollService
{
    private function admin(): void
    {
        abort_unless(auth()->user()?->hasRole('admin'), 403);
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['payroll' => $message]);
    }

    private function checkVersion(int $actual, int $expected): void
    {
        if ($actual !== $expected) {
            $this->fail('Dữ liệu lương đã thay đổi ở phiên khác. Hãy tải lại trang trước khi lưu.');
        }
    }

    public function saveProfile(User $employee, array $data): void
    {
        $this->admin();
        DB::transaction(function () use ($employee, $data) {
            $user = User::lockForUpdate()->findOrFail($employee->id);
            abort_unless($user->hasAnyRole(SalaryProfile::ROLES), 403);
            $profile = SalaryProfile::where('user_id', $user->id)->lockForUpdate()->first();
            $this->checkVersion($profile?->version ?? 0, (int) $data['version']);
            $old = $profile?->toArray() ?? [];
            $profile ??= new SalaryProfile(['user_id' => $user->id]);
            $profile->fill(['pay_basis' => $data['pay_basis'], 'unit_rate' => $data['unit_rate'],
                'version' => ($profile->version ?? 0) + 1, 'updated_by' => auth()->id()])->save();
            AuditLog::record('salary.profile_updated', $profile, $old, $profile->toArray());
        }, 5);
    }

    /** Snapshot ended scheduled shifts; attendance corrections remain explicit admin decisions. */
    private function source(User $user, string $month): array
    {
        $from = Carbon::createFromFormat('!Y-m', $month);
        $to = $from->copy()->endOfMonth();
        $doctor = $user->hasRole('doctor') ? $user->doctor : null;
        if ($user->hasRole('doctor') && ! $doctor) {
            $this->fail('Tài khoản chưa có hồ sơ bác sĩ để lấy ca làm.');
        }
        $query = $doctor ? DoctorSchedule::where('doctor_id', $doctor->id)->where('is_open', true)
            : StaffWorkShift::where('user_id', $user->id)->where('status', 'CONFIRMED');
        $shifts = $query->whereBetween('work_date', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->where(fn ($q) => $q->whereDate('work_date', '<', today())->orWhere(fn ($today) => $today->whereDate('work_date', today())->where('end_time', '<=', now()->format('H:i:s'))))
            ->with('branch')->orderBy('work_date')->orderBy('start_time')->get();
        $leave = $doctor ? DB::table('doctor_time_off')->where('doctor_id', $doctor->id)->where('starts_at', '<=', $to)->where('ends_at', '>=', $from)->get() : collect();
        $rows = [];
        $minutes = 0;
        $previousEnd = null;
        $excluded = 0;
        foreach ($shifts as $shift) {
            $start = Carbon::parse($shift->work_date->format('Y-m-d').' '.$shift->start_time);
            $end = Carbon::parse($shift->work_date->format('Y-m-d').' '.$shift->end_time);
            if ($end->lte($start)) {
                $this->fail('Ca làm có giờ kết thúc không hợp lệ. Hãy kiểm tra lịch nguồn.');
            }
            if ($leave->contains(fn ($l) => Carbon::parse($l->starts_at)->lt($end) && Carbon::parse($l->ends_at)->gt($start))) {
                $excluded++;

                continue;
            }
            if ($previousEnd && $start->lt($previousEnd)) {
                $this->fail('Lịch nguồn có ca trùng giờ, kể cả giữa cơ sở. Hãy điều chỉnh lịch trước khi tính lương.');
            }
            $duration = $start->diffInMinutes($end);
            $rows[] = ['type' => $doctor ? 'doctor_schedule' : 'staff_work_shift', 'id' => $shift->id,
                'date' => $start->format('Y-m-d'), 'start' => $start->format('H:i'), 'end' => $end->format('H:i'),
                'minutes' => $duration, 'branch' => $shift->branch->name];
            $minutes += $duration;
            $previousEnd = $end;
        }

        return ['worked_minutes' => $minutes, 'worked_shifts' => count($rows),
            'source_shifts' => ['shifts' => $rows, 'excluded_leave_shifts' => $excluded], 'calculated_at' => now()];
    }

    private function amounts(array $data): array
    {
        $base = $data['pay_basis'] === 'HOUR'
            ? intdiv((int) $data['unit_rate'] * (int) $data['worked_minutes'] + 30, 60)
            : (int) $data['unit_rate'] * (int) $data['worked_shifts'];
        $net = $base + (int) ($data['allowance'] ?? 0) + (int) ($data['bonus'] ?? 0) - (int) ($data['deduction'] ?? 0);
        if ($net < 0) {
            $this->fail('Khấu trừ không được lớn hơn tổng tiền lương, phụ cấp và thưởng.');
        }

        return ['base_amount' => $base, 'net_amount' => $net];
    }

    public function generate(User $employee, array $data): PayrollEntry
    {
        $this->admin();

        return DB::transaction(function () use ($employee, $data) {
            $user = User::lockForUpdate()->findOrFail($employee->id);
            abort_unless($user->hasAnyRole(SalaryProfile::ROLES), 403);
            $profile = SalaryProfile::where('user_id', $user->id)->lockForUpdate()->first();
            if (! $profile) {
                $this->fail('Hãy thiết lập đơn giá riêng cho nhân viên trước khi tính lương.');
            }
            $this->checkVersion($profile->version, (int) $data['profile_version']);
            $month = $data['month'].'-01';
            if (PayrollEntry::where('user_id', $user->id)->whereDate('pay_month', $month)->exists()) {
                $this->fail('Nhân viên đã có bảng lương tháng này. Mở bảng lương hiện có để kiểm tra hoặc cập nhật.');
            }
            $values = array_merge($this->source($user, $data['month']), [
                'user_id' => $user->id, 'employee_name' => $user->name, 'role_name' => $user->role->name,
                'pay_month' => $month, 'pay_basis' => $profile->pay_basis, 'unit_rate' => $profile->unit_rate,
                'allowance' => 0, 'bonus' => 0, 'deduction' => 0,
                'status' => 'UNPAID', 'version' => 1, 'created_by' => auth()->id(), 'updated_by' => auth()->id(),
            ]);
            $entry = PayrollEntry::create(array_merge($values, $this->amounts($values)));
            AuditLog::record('payroll.created', $entry, [], $entry->toArray());

            return $entry;
        }, 5);
    }

    private function lockedEntry(PayrollEntry $entry, int $version): PayrollEntry
    {
        User::whereKey($entry->user_id)->lockForUpdate()->firstOrFail();
        $fresh = PayrollEntry::lockForUpdate()->findOrFail($entry->id);
        $this->checkVersion($fresh->version, $version);

        return $fresh;
    }

    private function unpaid(PayrollEntry $entry): void
    {
        if ($entry->status !== 'UNPAID') {
            $this->fail('Bảng lương đã thanh toán không thể chỉnh số tiền. Admin cần chuyển về chưa thanh toán kèm lý do trước.');
        }
    }

    public function update(PayrollEntry $entry, array $data): void
    {
        $this->admin();
        DB::transaction(function () use ($entry, $data) {
            $fresh = $this->lockedEntry($entry, (int) $data['version']);
            $this->unpaid($fresh);
            $old = $fresh->toArray();
            $values = [
                'unit_rate' => (int) $data['unit_rate'], 'worked_minutes' => (int) $data['hours'] * 60 + (int) $data['minutes'],
                'worked_shifts' => (int) $data['worked_shifts'], 'allowance' => (int) $data['allowance'],
                'bonus' => (int) $data['bonus'], 'deduction' => (int) $data['deduction'],
                'note' => $data['note'] ?? null, 'adjustment_reason' => $data['reason'],
            ];
            if ($values['worked_minutes'] > $fresh->pay_month->daysInMonth * 1440) {
                $this->fail('Số giờ thực tế không được vượt tổng thời gian trong tháng.');
            }
            $fresh->fill(array_merge($values, $this->amounts(array_merge($fresh->toArray(), $values)),
                ['version' => $fresh->version + 1, 'updated_by' => auth()->id()]))->save();
            AuditLog::record('payroll.adjusted', $fresh, $old, $fresh->toArray());
        }, 5);
    }

    public function recalculate(PayrollEntry $entry, array $data): void
    {
        $this->admin();
        DB::transaction(function () use ($entry, $data) {
            $fresh = $this->lockedEntry($entry, (int) $data['version']);
            $this->unpaid($fresh);
            abort_unless($fresh->user->hasAnyRole(SalaryProfile::ROLES), 403);
            $old = $fresh->toArray();
            $values = $this->source($fresh->user, $fresh->pay_month->format('Y-m'));
            $fresh->fill(array_merge($values, $this->amounts(array_merge($fresh->toArray(), $values)),
                ['adjustment_reason' => $data['reason'], 'version' => $fresh->version + 1, 'updated_by' => auth()->id()]))->save();
            AuditLog::record('payroll.recalculated', $fresh, $old, $fresh->toArray());
        }, 5);
    }

    public function payment(PayrollEntry $entry, array $data): void
    {
        $this->admin();
        DB::transaction(function () use ($entry, $data) {
            $fresh = $this->lockedEntry($entry, (int) $data['version']);
            if ($fresh->status === $data['status']) {
                $this->fail('Bảng lương đã ở trạng thái này. Không ghi nhận thanh toán lần nữa.');
            }
            $old = $fresh->toArray();
            $paid = $data['status'] === 'PAID';
            $fresh->fill(['status' => $data['status'], 'paid_at' => $paid ? now() : null,
                'paid_by' => $paid ? auth()->id() : null, 'payment_reference' => $paid ? ($data['reference'] ?? null) : null,
                'version' => $fresh->version + 1, 'updated_by' => auth()->id()])->save();
            AuditLog::record($paid ? 'payroll.paid' : 'payroll.payment_reversed', $fresh, $old, array_merge($fresh->toArray(), ['reason' => $data['reason']]));
        }, 5);
    }
}
