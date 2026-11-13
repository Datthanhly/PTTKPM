<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Role;
use App\Models\ShiftTransferRequest;
use App\Models\Specialty;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DoctorCoverageService
{
    public function arrange(string $from, int $days, bool $includeSunday): array
    {
        abort_unless(auth()->user()?->hasRole('admin'), 403);
        $start = Carbon::createFromFormat('!Y-m-d', $from);
        $until = $start->copy()->addDays($days - 1);
        if ($days < 1 || $days > 90 || $start->lte(today()) || $until->gt(today()->addDays(90))) {
            throw ValidationException::withMessages(['coverage' => 'Chọn từ ngày mai, trong phạm vi 90 ngày và tối đa 90 ngày mỗi đợt.']);
        }

        return DB::transaction(function () use ($start, $until, $includeSunday) {
            $role = Role::where('code', 'doctor')->lockForUpdate()->firstOrFail();
            $branches = Branch::where('is_active', true)->orderBy('id')->get();
            $specialties = Specialty::where('is_active', true)->orderBy('id')->get();
            if ($branches->isEmpty() || $specialties->isEmpty()) {
                throw ValidationException::withMessages(['coverage' => 'Cần có cơ sở và chuyên khoa đang hoạt động.']);
            }
            $doctors = Doctor::with('user.branches')->where('is_active', true)
                ->whereHas('user', fn ($q) => $q->where('is_active', true)->where('status', 'ACTIVE')->where('role_id', $role->id))->orderBy('id')->get();
            $createdDoctors = [];
            foreach ($branches as $branch) {
                foreach ($specialties as $specialty) {
                    $pool = $doctors->filter(fn ($d) => $d->specialty_id === $specialty->id && $d->user->branches->contains('id', $branch->id));
                    while ($pool->count() < config('clinic_workforce.doctors_per_specialty_per_branch', 2)) {
                        $doctor = $this->addDoctor($branch, $specialty, $role, $doctors->where('specialty_id', $specialty->id)->count());
                        $doctors->push($doctor);
                        $pool->push($doctor);
                        $createdDoctors[] = $doctor->id;
                    }
                }
            }
            // Same ordered doctor locks as booking/shift transfer: a concurrent booking
            // cannot attach a patient to a shift while it is being reassigned.
            foreach ($doctors->sortBy('id') as $doctor) {
                app(AppointmentBookingService::class)->lockDoctor($doctor->id);
            }
            $doctorMap = $doctors->keyBy('id');
            $shifts = DoctorSchedule::whereDate('work_date', '>=', $start)->whereDate('work_date', '<=', $until)->orderBy('id')->get();
            $referenced = Appointment::whereIn('doctor_schedule_id', $shifts->pluck('id'))->pluck('doctor_schedule_id')
                ->merge(ShiftTransferRequest::whereIn('doctor_schedule_id', $shifts->pluck('id'))->pluck('doctor_schedule_id'))->unique()->flip();
            $periods = config('clinic.working_hours');
            $movable = $shifts->filter(function ($s) use ($referenced, $doctorMap, $branches, $specialties, $periods, $includeSunday) {
                $doctor = $doctorMap->get($s->doctor_id);

                return $s->is_open && ! $referenced->has($s->id) && $doctor
                    && $branches->contains('id', $s->branch_id) && $specialties->contains('id', $doctor->specialty_id)
                    && ($includeSunday || ! $s->work_date->isSunday())
                    && collect($periods)->contains(fn ($p) => substr($s->start_time, 0, 5) === $p[0] && substr($s->end_time, 0, 5) === $p[1]);
            });
            $movableIds = $movable->pluck('id')->flip();
            $fixed = $shifts->reject(fn ($s) => $movableIds->has($s->id));
            $fixedByBranchDay = $fixed->groupBy(fn ($s) => $s->branch_id.'|'.$s->work_date->format('Y-m-d'));
            $shiftsByDoctorDay = $shifts->groupBy(fn ($s) => $s->doctor_id.'|'.$s->work_date->format('Y-m-d'));
            $leave = DB::table('doctor_time_off')->whereIn('doctor_id', $doctors->pluck('id'))->where('starts_at', '<', $until->copy()->endOfDay())->where('ends_at', '>', $start)->get()->groupBy('doctor_id');
            $appointments = Appointment::whereIn('doctor_id', $doctors->pluck('id'))->whereDate('appointment_date', '>=', $start)->whereDate('appointment_date', '<=', $until)->whereNotIn('status', ['CANCELLED', 'NO_SHOW'])->get();
            $busy = [];
            $loads = [];
            foreach ($fixed as $s) {
                $this->reserve($busy, $s->doctor_id, $s->work_date->format('Y-m-d'), $s->start_time, $s->end_time);
                if ($s->is_open) {
                    $this->addLoad($loads, $s->doctor_id, $s->work_date->format('Y-m-d'), $s->start_time, $s->end_time);
                }
            }
            foreach ($appointments as $a) {
                $this->reserve($busy, $a->doctor_id, $a->appointment_date->format('Y-m-d'), $a->start_time, $a->end_time);
            }
            $groups = $movable->groupBy(fn ($s) => $this->key($s->branch_id, $doctorMap[$s->doctor_id]->specialty_id, $s->work_date->format('Y-m-d'), substr($s->start_time, 0, 5)));
            $plan = [];
            $used = [];
            $checked = 0;
            for ($date = $start->copy(); $date->lte($until); $date->addDay()) {
                if (! $includeSunday && $date->isSunday()) {
                    continue;
                }
                $day = $date->format('Y-m-d');
                foreach ($branches as $branch) {
                    foreach ($specialties as $specialty) {
                        $pool = $doctors->filter(fn ($d) => $d->specialty_id === $specialty->id && $d->user->branches->contains('id', $branch->id))->values();
                        foreach (array_values($periods) as $index => [$begin, $end]) {
                            $checked++;
                            $covered = $fixedByBranchDay->get($branch->id.'|'.$day, collect())->contains(fn ($s) => $s->is_open && $s->branch_id === $branch->id
                                && $s->work_date->format('Y-m-d') === $day && substr($s->start_time, 0, 5) <= $begin && substr($s->end_time, 0, 5) >= $end
                                && $pool->contains('id', $s->doctor_id) && ! $this->onLeave($leave, $s->doctor_id, $day, $begin, $end));
                            if ($covered) {
                                continue;
                            }
                            $week = $date->copy()->startOfWeek()->format('Y-m-d');
                            $rotation = ((int) $date->format('z') + $index) % $pool->count();
                            $candidates = $pool->filter(fn ($d) => ! $this->overlaps($busy[$d->id][$day] ?? [], $begin, $end)
                                && ! $shiftsByDoctorDay->get($d->id.'|'.$day, collect())->contains(fn ($s) => $s->branch_id !== $branch->id && substr($s->start_time, 0, 5) < $end && substr($s->end_time, 0, 5) > $begin)
                                && ! $this->onLeave($leave, $d->id, $day, $begin, $end))
                                ->sortBy(fn ($d) => [$loads[$d->id][$week] ?? 0, ($pool->search(fn ($p) => $p->id === $d->id) - $rotation + $pool->count()) % $pool->count(), $d->id]);
                            $chosen = $candidates->first();
                            if (! $chosen) {
                                throw ValidationException::withMessages(['coverage' => 'Không có bác sĩ trống cho '.$specialty->name.' tại '.$branch->name.' ngày '.$date->format('d/m/Y').' '.$begin.'–'.$end.'. Không lưu đợt xếp lịch này.']);
                            }
                            $existing = $groups->get($this->key($branch->id, $specialty->id, $day, $begin), collect());
                            $reuse = $existing->firstWhere('doctor_id', $chosen->id) ?? $existing->first();
                            $plan[] = ['schedule' => $reuse, 'doctor_id' => $chosen->id, 'branch_id' => $branch->id, 'work_date' => $day, 'start_time' => $begin, 'end_time' => $end];
                            if ($reuse) {
                                $used[$reuse->id] = true;
                            }
                            $this->reserve($busy, $chosen->id, $day, $begin, $end);
                            $this->addLoad($loads, $chosen->id, $day, $begin, $end);
                        }
                    }
                }
            }
            $created = 0;
            $reassigned = 0;
            $closed = 0;
            foreach ($plan as $assignment) {
                $schedule = $assignment['schedule'];
                unset($assignment['schedule']);
                if ($schedule) {
                    if ($schedule->doctor_id !== $assignment['doctor_id']) {
                        $old = $schedule->only(['doctor_id', 'branch_id', 'work_date', 'start_time', 'end_time']);
                        $schedule->update(['doctor_id' => $assignment['doctor_id']]);
                        AuditLog::record('schedule.coverage_reassigned', $schedule, $old, $schedule->only(array_keys($old)));
                        $reassigned++;
                    }
                } else {
                    $schedule = DoctorSchedule::create([...$assignment, 'slot_minutes' => config('clinic.slot_minutes'), 'capacity' => 1, 'is_open' => true]);
                    AuditLog::record('schedule.coverage_created', $schedule, [], $schedule->only(['doctor_id', 'branch_id', 'work_date', 'start_time', 'end_time']));
                    $created++;
                }
            }
            foreach ($movable as $schedule) {
                if (! isset($used[$schedule->id])) {
                    $schedule->update(['is_open' => false]);
                    AuditLog::record('schedule.coverage_closed', $schedule, ['is_open' => true], ['is_open' => false, 'reason' => 'Ca trống dư sau khi phân bổ bảo đảm mỗi khoa/cơ sở có bác sĩ trực.']);
                    $closed++;
                }
            }

            return ['created_doctors' => $createdDoctors, 'created_shifts' => $created, 'reassigned_shifts' => $reassigned,
                'closed_empty_shifts' => $closed, 'protected_shifts' => $referenced->count(), 'checked_cells' => $checked,
                'from' => $start->format('Y-m-d'), 'until' => $until->format('Y-m-d'), 'include_sunday' => $includeSunday,
                'branch_count' => $branches->count(), 'specialty_count' => $specialties->count()];
        }, 5);
    }

    private function key(int $branch, int $specialty, string $date, string $start): string
    {
        return "$branch|$specialty|$date|$start";
    }

    private function reserve(array &$busy, int $doctor, string $date, string $start, string $end): void
    {
        $busy[$doctor][$date][] = [substr($start, 0, 5), substr($end, 0, 5)];
    }

    private function overlaps(array $intervals, string $start, string $end): bool
    {
        return collect($intervals)->contains(fn ($i) => $i[0] < $end && $i[1] > $start);
    }

    private function onLeave($leave, int $doctor, string $date, string $start, string $end): bool
    {
        return $leave->get($doctor, collect())->contains(fn ($l) => Carbon::parse($l->starts_at)->lt(Carbon::parse($date.' '.$end)) && Carbon::parse($l->ends_at)->gt(Carbon::parse($date.' '.$start)));
    }

    private function addLoad(array &$loads, int $doctor, string $date, string $start, string $end): void
    {
        $week = Carbon::parse($date)->startOfWeek()->format('Y-m-d');
        $loads[$doctor][$week] = ($loads[$doctor][$week] ?? 0) + Carbon::parse($start)->diffInMinutes(Carbon::parse($end));
    }

    private function addDoctor(Branch $branch, Specialty $specialty, Role $role, int $position): Doctor
    {
        do {
            $name = config('clinic_workforce.doctor_names.'.$specialty->code.'.'.$position, $specialty->name.' '.$branch->code.' '.($position + 1));
            $position++;
        } while (User::where('name', 'BS. '.$name)->exists());
        $base = 'bacsi.'.Str::slug($specialty->code).'.'.Str::slug($branch->code);
        $email = $base.'@clinic.test';
        for ($suffix = 2; User::where('email', $email)->exists(); $suffix++) {
            $email = $base.'.'.$suffix.'@clinic.test';
        }
        $user = User::create(['role_id' => $role->id, 'name' => 'BS. '.$name, 'email' => $email, 'password' => 'Password@123']);
        $user->branches()->attach($branch->id);
        for ($number = Doctor::max('id') + 1; ; $number++) {
            $code = sprintf('BS%04d', $number);
            if (! Doctor::where('doctor_code', $code)->orWhere('license_number', 'DEMO-GPHN-'.$code)->exists()) {
                break;
            }
        }
        $doctor = Doctor::create(['user_id' => $user->id, 'specialty_id' => $specialty->id, 'doctor_code' => $code,
            'license_number' => 'DEMO-GPHN-'.$code, 'qualification' => 'Bác sĩ chuyên khoa '.$specialty->name,
            'room' => 'Phòng '.(200 + $specialty->id), 'bio' => 'Hồ sơ bác sĩ giả lập phục vụ đồ án, không đại diện người hành nghề thực tế.']);
        $doctor->specialties()->attach($specialty->id);
        $doctor->setRelation('user', $user->load('branches'));
        app(PayrollService::class)->saveProfile($user, ['version' => 0, 'pay_basis' => 'HOUR', 'unit_rate' => config('clinic_workforce.new_doctor_hourly_rate', 500000)]);
        AuditLog::record('workforce.coverage_doctor_added', $doctor, [], ['branch_id' => $branch->id, 'specialty_id' => $specialty->id]);

        return $doctor;
    }
}
