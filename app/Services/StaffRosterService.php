<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Role;
use App\Models\StaffScheduleRequest;
use App\Models\StaffWorkShift;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StaffRosterService
{
    public function lock(): void
    {
        // All roster writes and staff reassignment use the same short, ordered lock.
        Role::whereIn('code', array_keys(config('staff_roster.roles')))->orderBy('id')->lockForUpdate()->get();
    }

    public function week(string $date): Carbon
    {
        return Carbon::parse($date)->startOfWeek(Carbon::MONDAY);
    }

    public function shifts(string $date, ?int $branchId = null): Builder
    {
        $from = $this->week($date);

        return StaffWorkShift::whereBetween('work_date', [$from->format('Y-m-d'), $from->copy()->addDays(6)->format('Y-m-d')])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));
    }

    public function fingerprint(Collection $shifts): string
    {
        return hash('sha256', $shifts->sortBy('id')->map(fn ($shift) => $shift->version())->implode('|'));
    }

    public function confirmedUpcoming(): Builder
    {
        return StaffWorkShift::where('status', 'CONFIRMED')->where(fn ($q) => $q->whereDate('work_date', '>', today())
            ->orWhere(fn ($day) => $day->whereDate('work_date', today())->where('end_time', '>', now()->format('H:i:s'))));
    }

    public function submit(array $data): Collection
    {
        $user = auth()->user();
        abort_unless($user?->hasAnyRole(array_keys(config('staff_roster.roles'))), 403);

        return DB::transaction(function () use ($data, $user) {
            $this->lock();
            $role = $user->fresh()->role->code;
            $this->ensure($this->eligibility($user->id, (int) $data['branch_id'], $role));
            $periods = $data['session'] === 'both' ? array_values(config('clinic.working_hours')) : [config('clinic.working_hours.'.$data['session'])];
            $created = collect();
            for ($date = Carbon::parse($data['from_date']); $date->lte(Carbon::parse($data['to_date'])); $date->addDay()) {
                if (! in_array($date->dayOfWeekIso, array_map('intval', $data['weekdays']), true)) {
                    continue;
                }
                foreach ($periods as [$start, $end]) {
                    $day = $date->format('Y-m-d');
                    $this->ensure(! Carbon::parse($day.' '.$start)->isFuture() ? 'Không thể đăng ký ca đã bắt đầu ngày '.$date->format('d/m/Y').'.' : null);
                    $duplicate = StaffScheduleRequest::where('user_id', $user->id)->whereDate('work_date', $day)->whereIn('status', StaffScheduleRequest::ACTIVE)
                        ->where('start_time', '<', $end)->where('end_time', '>', $start)->exists();
                    $this->ensure($duplicate ? 'Bạn đã đăng ký hoặc đã được xếp ca trùng giờ ngày '.$date->format('d/m/Y').', kể cả ở cơ sở khác.' : null);
                    $this->ensure($this->scheduleError($user->id, (int) $data['branch_id'], $role, $day, $start, $end));
                    $request = StaffScheduleRequest::create([
                        'user_id' => $user->id, 'branch_id' => $data['branch_id'], 'role_code' => $role,
                        'work_date' => $day, 'start_time' => $start, 'end_time' => $end, 'note' => $data['note'] ?? null,
                        'status' => 'WAITING', 'active_key' => hash('sha256', $user->id.'|'.$day.'|'.$start.'|'.$end),
                    ]);
                    AuditLog::record('staff_roster.requested', $request);
                    $created->push($request);
                }
            }
            $this->ensure($created->isEmpty() ? 'Khoảng ngày đã chọn không có thứ nào được đánh dấu.' : null);

            return $created;
        });
    }

    public function arrange(StaffScheduleRequest $request, array $data): StaffWorkShift
    {
        $this->admin();

        return DB::transaction(function () use ($request, $data) {
            $this->lock();
            $request = StaffScheduleRequest::lockForUpdate()->findOrFail($request->id);
            $shift = $request->shift()->lockForUpdate()->first();
            $this->ensure(($shift?->version() ?? 'new') !== $data['plan_version'] ? 'Ca dự kiến đã thay đổi. Hãy tải lại trang trước khi chỉnh sửa.' : null);

            return $this->arrangeLocked($request, $data);
        });
    }

    private function arrangeLocked(StaffScheduleRequest $request, array $data): StaffWorkShift
    {
        $this->ensure(! in_array($request->status, ['WAITING', 'ARRANGED'], true) ? 'Chỉ xếp đăng ký đang chờ hoặc đang ở lịch dự kiến.' : null);
        $shift = $request->shift()->first();
        $this->ensure($shift && $shift->status !== 'DRAFT' ? 'Ca đã chốt phải được mở chốt trước khi điều chỉnh.' : null);
        $this->ensure($this->scheduleError($request->user_id, (int) $data['branch_id'], $request->role_code, $data['work_date'], $data['start_time'], $data['end_time'], $shift?->id));
        $old = $shift?->toArray() ?? [];
        $shift ??= new StaffWorkShift(['request_id' => $request->id, 'created_by' => auth()->id()]);
        $shift->fill([
            'user_id' => $request->user_id, 'role_code' => $request->role_code,
            'branch_id' => $data['branch_id'], 'work_date' => $data['work_date'], 'start_time' => $data['start_time'], 'end_time' => $data['end_time'],
            'note' => $data['note'] ?? null, 'status' => 'DRAFT', 'published_by' => null, 'published_at' => null,
        ])->save();
        $request->update(['status' => 'ARRANGED', 'processed_by' => auth()->id(), 'processed_at' => now(), 'decision_note' => $data['note'] ?? null]);
        AuditLog::record('staff_roster.arranged', $shift, $old, $shift->toArray());

        return $shift;
    }

    public function autoArrange(string $date, ?int $branchId, array $needs): array
    {
        $this->admin();

        return DB::transaction(function () use ($date, $branchId, $needs) {
            $this->lock();
            $from = $this->week($date);
            $branches = Branch::where('is_active', true)->when($branchId, fn ($q) => $q->whereKey($branchId))->orderBy('id')->get();
            $this->ensure($branches->isEmpty() ? 'Không có cơ sở hoạt động phù hợp.' : null);
            $waiting = StaffScheduleRequest::with('user')->whereIn('branch_id', $branches->pluck('id'))
                ->whereBetween('work_date', [$from->format('Y-m-d'), $from->copy()->addDays(6)->format('Y-m-d')])->where('status', 'WAITING')->orderBy('id')->get();
            $loads = $this->shifts($date)->whereIn('status', StaffWorkShift::ACTIVE)->get()->groupBy('user_id')->map(fn ($rows) => $rows->sum(fn ($s) => $s->minutes()))->all();
            $result = ['created' => 0, 'unfilled' => 0, 'skipped' => []];
            foreach ($branches as $branch) {
                for ($offset = 0; $offset < 7; $offset++) {
                    $day = $from->copy()->addDays($offset)->format('Y-m-d');
                    if ($offset === 6 && ! $waiting->contains(fn ($r) => $r->branch_id === $branch->id && $r->work_date->format('Y-m-d') === $day)) {
                        continue;
                    }
                    foreach (config('clinic.working_hours') as [$start, $end]) {
                        if (! Carbon::parse($day.' '.$start)->isFuture()) {
                            continue;
                        }
                        foreach ($needs as $role => $required) {
                            $covered = StaffWorkShift::where('branch_id', $branch->id)->whereDate('work_date', $day)->where('role_code', $role)->whereIn('status', StaffWorkShift::ACTIVE)
                                ->where('start_time', '<=', $start)->where('end_time', '>=', $end)->count();
                            $candidates = $waiting->filter(fn ($r) => $r->status === 'WAITING' && $r->branch_id === $branch->id && $r->role_code === $role
                                && $r->work_date->format('Y-m-d') === $day && substr($r->start_time, 0, 5) === $start && substr($r->end_time, 0, 5) === $end)
                                ->sort(fn ($a, $b) => [$loads[$a->user_id] ?? 0, $a->user_id, $a->id] <=> [$loads[$b->user_id] ?? 0, $b->user_id, $b->id]);
                            foreach ($candidates as $candidate) {
                                if ($covered >= (int) $required) {
                                    break;
                                }
                                $error = $this->scheduleError($candidate->user_id, $branch->id, $role, $day, $start, $end);
                                if ($error !== null) {
                                    $result['skipped'][$candidate->id] = $candidate->user->name.': '.$error;

                                    continue;
                                }
                                $shift = $this->arrangeLocked($candidate, ['branch_id' => $branch->id, 'work_date' => $day, 'start_time' => $start, 'end_time' => $end, 'note' => 'Xếp tự động theo đăng ký và tổng thời gian làm trong tuần.']);
                                $loads[$candidate->user_id] = ($loads[$candidate->user_id] ?? 0) + $shift->minutes();
                                $covered++;
                                $result['created']++;
                            }
                            $result['unfilled'] += max(0, (int) $required - $covered);
                        }
                    }
                }
            }
            $result['skipped'] = array_values($result['skipped']);

            return $result;
        });
    }

    public function publish(string $date, ?int $branchId, string $version): int
    {
        $this->admin();

        return DB::transaction(function () use ($date, $branchId, $version) {
            $this->lock();
            $shifts = $this->shifts($date, $branchId)->where('status', 'DRAFT')->orderBy('id')->lockForUpdate()->get();
            $this->ensure($shifts->isEmpty() ? 'Chưa có ca dự kiến nào để chốt.' : null);
            $this->ensure($this->fingerprint($shifts) !== $version ? 'Lịch dự kiến đã thay đổi. Hãy tải lại và xem lại các ca trước khi chốt.' : null);
            foreach ($shifts as $shift) {
                $request = $shift->request()->lockForUpdate()->firstOrFail();
                $this->ensure($request->status !== 'ARRANGED' ? 'Đăng ký đã thay đổi, không thể chốt lịch.' : null);
                $this->ensure($this->scheduleError($shift->user_id, $shift->branch_id, $shift->role_code, $shift->work_date->format('Y-m-d'), $shift->start_time, $shift->end_time, $shift->id));
                $shift->update(['status' => 'CONFIRMED', 'published_by' => auth()->id(), 'published_at' => now()]);
                $request->update(['status' => 'CONFIRMED', 'processed_by' => auth()->id(), 'processed_at' => now()]);
                AuditLog::record('staff_roster.published', $shift);
            }

            return $shifts->count();
        });
    }

    public function reopen(StaffWorkShift $shift, string $reason, string $version): void
    {
        $this->admin();
        DB::transaction(function () use ($shift, $reason, $version) {
            $this->lock();
            $shift = StaffWorkShift::lockForUpdate()->findOrFail($shift->id);
            $this->ensure($shift->status !== 'CONFIRMED' || ! $shift->startsAt()->isFuture() ? 'Chỉ mở chốt ca chính thức chưa bắt đầu.' : null);
            $this->ensure($shift->version() !== $version ? 'Ca làm đã thay đổi. Hãy tải lại trước khi mở chốt.' : null);
            $old = $shift->toArray();
            $shift->update(['status' => 'DRAFT', 'published_by' => null, 'published_at' => null, 'note' => $reason]);
            $shift->request()->update(['status' => 'ARRANGED', 'decision_note' => $reason, 'processed_by' => auth()->id(), 'processed_at' => now()]);
            AuditLog::record('staff_roster.reopened', $shift, $old, ['reason' => $reason]);
        });
    }

    public function withdraw(StaffScheduleRequest $request): void
    {
        abort_unless(auth()->user()?->hasAnyRole(array_keys(config('staff_roster.roles'))) && $request->user_id === auth()->id(), 403);
        $this->closeRequest($request, 'CANCELLED', 'Nhân viên rút đăng ký.');
    }

    public function reject(StaffScheduleRequest $request, string $reason): void
    {
        $this->admin();
        $this->closeRequest($request, 'REJECTED', $reason);
    }

    private function closeRequest(StaffScheduleRequest $request, string $status, string $reason): void
    {
        DB::transaction(function () use ($request, $status, $reason) {
            $this->lock();
            $request = StaffScheduleRequest::lockForUpdate()->findOrFail($request->id);
            if ($status === 'CANCELLED') {
                abort_unless($request->user_id === auth()->id(), 403);
            }
            $this->ensure(! in_array($request->status, ['WAITING', 'ARRANGED'], true) ? 'Chỉ rút hoặc từ chối đăng ký chưa chốt. Ca đã chốt cần admin mở chốt trước.' : null);
            $shift = $request->shift()->lockForUpdate()->first();
            $this->ensure($shift && $shift->status !== 'DRAFT' ? 'Ca không còn ở trạng thái dự kiến.' : null);
            $shift?->update(['status' => 'CANCELLED']);
            $request->update(['status' => $status, 'active_key' => null, 'decision_note' => $reason, 'processed_by' => auth()->id(), 'processed_at' => now()]);
            AuditLog::record('staff_roster.'.strtolower($status), $request, [], ['reason' => $reason]);
        });
    }

    private function eligibility(int $userId, int $branchId, string $role): ?string
    {
        $user = User::with('role')->findOrFail($userId);
        if (! array_key_exists($role, config('staff_roster.roles')) || $user->role->code !== $role || ! $user->is_active || $user->status !== 'ACTIVE') {
            return 'Nhân viên không còn hoạt động hoặc vai trò đã thay đổi. Cần đăng ký lại sau khi cập nhật phân công.';
        }
        if (! $user->branches()->where('branches.id', $branchId)->where('is_active', true)->exists()) {
            return 'Nhân viên chưa được phân công tại cơ sở đang hoạt động này.';
        }

        return null;
    }

    private function scheduleError(int $userId, int $branchId, string $role, string $date, string $start, string $end, ?int $ignoreId = null): ?string
    {
        if ($error = $this->eligibility($userId, $branchId, $role)) {
            return $error;
        }
        $from = Carbon::parse($date.' '.$start);
        $until = Carbon::parse($date.' '.$end);
        if (! $from->isFuture() || $until->lte($from)) {
            return 'Ca làm phải chưa bắt đầu và giờ kết thúc phải sau giờ bắt đầu.';
        }
        $others = StaffWorkShift::where('user_id', $userId)->whereDate('work_date', $date)->whereIn('status', StaffWorkShift::ACTIVE)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->get();
        if ($others->contains(fn ($s) => substr($s->start_time, 0, 5) < substr($end, 0, 5) && substr($s->end_time, 0, 5) > substr($start, 0, 5))) {
            return 'Trùng ca dự kiến hoặc ca đã chốt của nhân viên, kể cả tại cơ sở khác.';
        }
        if ($from->diffInMinutes($until) + $others->sum(fn ($s) => $s->minutes()) > config('staff_roster.max_daily_minutes')) {
            return 'Tổng giờ làm trong ngày vượt giới hạn '.(config('staff_roster.max_daily_minutes') / 60).' giờ trong cấu hình.';
        }

        return null;
    }

    private function admin(): void
    {
        abort_unless(auth()->user()?->hasRole('admin') && auth()->user()->hasPermission('SCHEDULE_MANAGE'), 403);
    }

    private function ensure(?string $message): void
    {
        if ($message !== null) {
            throw ValidationException::withMessages(['roster' => $message]);
        }
    }
}
