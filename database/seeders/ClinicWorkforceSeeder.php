<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Role;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ClinicWorkforceSeeder extends Seeder
{
    public array $createdDoctorIds = [];

    public array $createdStaffIds = [];

    public int $createdSchedules = 0;

    public function run(): void
    {
        $this->createdDoctorIds = [];
        $this->createdStaffIds = [];
        $this->createdSchedules = 0;
        DB::transaction(function () {
            $branches = Branch::where('is_active', true)->orderBy('id')->get();
            $specialties = Specialty::where('is_active', true)->orderBy('id')->get();
            $roles = Role::whereIn('code', ['doctor', 'receptionist', 'cashier'])->get()->keyBy('code');
            if ($branches->isEmpty() || $specialties->isEmpty() || $roles->count() !== 3) {
                throw new RuntimeException('Cần cơ sở, chuyên khoa đang hoạt động và đủ vai trò bác sĩ/lễ tân/thu ngân. Hãy chạy setup trước.');
            }
            // Count active primary-specialty doctors; never change existing users or assignments.
            $doctors = Doctor::with('user.branches')->where('is_active', true)
                ->whereHas('user', fn ($q) => $q->where('is_active', true)->where('status', 'ACTIVE')->where('role_id', $roles['doctor']->id))->get();
            foreach ($specialties as $specialty) {
                $count = $doctors->where('specialty_id', $specialty->id)->count();
                for ($position = $count; $position < config('clinic_workforce.doctors_per_specialty'); $position++) {
                    $inSpecialty = [];
                    $inBranch = [];
                    foreach ($branches as $branch) {
                        $assigned = $doctors->filter(fn ($d) => $d->user->branches->contains('id', $branch->id));
                        $inBranch[$branch->id] = $assigned->count();
                        $inSpecialty[$branch->id] = $assigned->where('specialty_id', $specialty->id)->count();
                    }
                    $branch = $branches->sort(fn ($a, $b) => [$inSpecialty[$a->id], $inBranch[$a->id], $a->id] <=> [$inSpecialty[$b->id], $inBranch[$b->id], $b->id])->first();
                    $name = config('clinic_workforce.doctor_names.'.$specialty->code.'.'.$position, $specialty->name.' '.($position + 1));
                    $user = User::create([
                        'role_id' => $roles['doctor']->id, 'name' => 'BS. '.$name,
                        'email' => $this->email('bacsi.'.Str::slug($specialty->code).'.'.Str::slug($branch->code)),
                        'password' => 'Password@123',
                    ]);
                    $user->branches()->attach($branch->id);
                    $code = $this->doctorCode();
                    $doctor = Doctor::create([
                        'user_id' => $user->id, 'specialty_id' => $specialty->id,
                        'doctor_code' => $code, 'license_number' => 'DEMO-GPHN-'.$code,
                        'qualification' => 'Bác sĩ chuyên khoa '.$specialty->name,
                        'room' => 'Phòng '.(200 + $specialty->id),
                        'bio' => 'Hồ sơ bác sĩ giả lập phục vụ đồ án quản lý phòng khám, không đại diện người hành nghề thực tế.',
                    ]);
                    $doctor->specialties()->attach($specialty->id);
                    $doctor->setRelation('user', $user->load('branches'));
                    $doctors->push($doctor);
                    $this->createdDoctorIds[] = $doctor->id;
                    $this->addSchedules($doctor, $branch);
                    AuditLog::record('seed.workforce_doctor_added', $doctor, [], ['branch_id' => $branch->id, 'specialty_id' => $specialty->id]);
                }
            }
            foreach (['receptionist' => 'letan', 'cashier' => 'thungan'] as $role => $prefix) {
                $namePosition = User::where('role_id', $roles[$role]->id)->count();
                foreach ($branches as $branch) {
                    $count = $branch->users()->where('role_id', $roles[$role]->id)->where('is_active', true)->where('status', 'ACTIVE')->count();
                    for ($position = $count; $position < config('clinic_workforce.staff_per_role_per_branch'); $position++) {
                        $name = config('clinic_workforce.staff_names.'.$role.'.'.$namePosition++, $roles[$role]->name.' '.$branch->code.' '.($position + 1));
                        $user = User::create([
                            'role_id' => $roles[$role]->id, 'name' => $name,
                            'email' => $this->email($prefix.'.'.Str::slug($branch->code).'.'.($position + 1)),
                            'password' => 'Password@123',
                        ]);
                        $user->branches()->attach($branch->id);
                        $this->createdStaffIds[] = $user->id;
                        AuditLog::record('seed.workforce_staff_added', $user, [], ['branch_id' => $branch->id, 'role' => $role]);
                    }
                }
            }
        });
    }

    private function email(string $base): string
    {
        $email = $base.'@clinic.test';
        for ($suffix = 2; User::where('email', $email)->exists(); $suffix++) {
            $email = $base.'.'.$suffix.'@clinic.test';
        }

        return $email;
    }

    private function doctorCode(): string
    {
        for ($number = 1; ; $number++) {
            $code = sprintf('BS%04d', $number);
            if (! Doctor::where('doctor_code', $code)->orWhere('license_number', 'DEMO-GPHN-'.$code)->exists()) {
                return $code;
            }
        }
    }

    private function addSchedules(Doctor $doctor, Branch $branch): void
    {
        // Only brand-new doctors receive default shifts. Existing schedules are untouched.
        for ($offset = 0; $offset < config('clinic_workforce.schedule_days'); $offset++) {
            $date = today()->addDays($offset);
            if ($date->isSunday()) {
                continue;
            }
            foreach (config('clinic.working_hours') as [$start, $end]) {
                if ($date->copy()->setTimeFromTimeString($start)->lte(now())) {
                    continue;
                }
                DoctorSchedule::create([
                    'doctor_id' => $doctor->id, 'branch_id' => $branch->id, 'work_date' => $date,
                    'start_time' => $start, 'end_time' => $end, 'slot_minutes' => config('clinic.slot_minutes'),
                    'capacity' => 1, 'is_open' => true,
                ]);
                $this->createdSchedules++;
            }
        }
    }
}
