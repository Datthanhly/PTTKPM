<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\Patient;
use App\Models\Role;
use App\Models\Service;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Demo seed is safe to rerun; application data is never reset by setup.
        if (User::where('email', 'admin@clinic.test')->exists()) {
            $this->call(ClinicUpgradeSeeder::class);
            $this->call(ClinicSpecialtySeeder::class);

            return;
        }
        $branch = Branch::firstOrCreate(['code' => 'CN01'], ['name' => 'Phòng khám An Tâm - Chi nhánh 1']);
        $branch2 = Branch::firstOrCreate(['code' => 'CN02'], ['name' => 'Phòng khám An Tâm - Chi nhánh 2', 'address' => 'Địa chỉ mô phỏng chi nhánh 2']);
        $roles = collect([
            'patient' => 'Bệnh nhân', 'receptionist' => 'Lễ tân', 'doctor' => 'Bác sĩ',
            'cashier' => 'Thu ngân', 'admin' => 'Quản trị viên',
        ])->mapWithKeys(fn ($name, $code) => [$code => Role::create(compact('code', 'name'))]);

        foreach ([
            ['TM', 'Tim mạch', 'Khám, tư vấn và theo dõi bệnh lý tim mạch.'],
            ['NOI', 'Nội khoa', 'Khám sức khỏe và bệnh lý nội khoa thông thường.'],
            ['NHI', 'Nhi khoa', 'Chăm sóc sức khỏe trẻ em.'],
        ] as [$code, $name, $description]) {
            Specialty::create(compact('code', 'name', 'description'));
        }
        $this->call(ClinicSpecialtySeeder::class);

        foreach ([
            ['DV001', 'Khám tổng quát', 200000], ['DV002', 'Điện tâm đồ', 180000],
            ['DV003', 'Siêu âm', 300000], ['DV004', 'Xét nghiệm máu cơ bản', 250000],
        ] as [$code, $name, $price]) {
            Service::create(compact('code', 'name', 'price'));
        }

        $staff = [
            ['admin', 'Quản trị hệ thống', 'admin@clinic.test', '0900000001'],
            ['receptionist', 'Lễ tân Demo', 'letan@clinic.test', '0900000002'],
            ['cashier', 'Thu ngân Demo', 'thungan@clinic.test', '0900000003'],
        ];
        foreach ($staff as [$role, $name, $email, $phone]) {
            $u = User::create(['role_id' => $roles[$role]->id, 'name' => $name, 'email' => $email, 'phone' => $phone, 'password' => Hash::make('Password@123')]);
            $u->branches()->attach($branch->id);
        }

        $doctorData = [
            ['BS0001', 'BS. Nguyễn Minh An', 'bacsi.an@clinic.test', '0901000001', 'GPHN-10001', 'TM', 'Phòng 201'],
            ['BS0002', 'BS. Trần Thu Hà', 'bacsi.ha@clinic.test', '0901000002', 'GPHN-10002', 'NOI', 'Phòng 202'],
            ['BS0003', 'BS. Lê Hoàng Nam', 'bacsi.nam@clinic.test', '0901000003', 'GPHN-10003', 'NHI', 'Phòng 203'],
        ];
        $doctors = collect();
        foreach ($doctorData as [$doctorCode, $name, $email, $phone, $license, $specialtyCode, $room]) {
            $user = User::create(['role_id' => $roles['doctor']->id, 'name' => $name, 'email' => $email, 'phone' => $phone, 'password' => Hash::make('Password@123')]);
            $doctors->push(Doctor::create([
                'user_id' => $user->id, 'specialty_id' => Specialty::where('code', $specialtyCode)->value('id'),
                'doctor_code' => $doctorCode, 'license_number' => $license,
                'qualification' => 'Bác sĩ chuyên khoa', 'room' => $room,
            ]));
            $user->branches()->attach($doctorCode === 'BS0003' ? $branch2->id : $branch->id);
            $doctors->last()->specialties()->attach($doctors->last()->specialty_id);
        }
        // Explicit demo grants, visible and editable under admin settings.
        foreach (['LAB_RESULT_ENTER', 'LAB_RESULT_VERIFY', 'MEDICINE_DISPENSE'] as $permission) {
            DB::table('user_permissions')->insert([
                'user_id' => $doctors->first()->user_id, 'branch_id' => $branch->id, 'permission_code' => $permission,
                'granted_by' => User::where('email', 'admin@clinic.test')->value('id'), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        foreach (['receptionist' => 'letan.cn2@clinic.test', 'cashier' => 'thungan.cn2@clinic.test'] as $role => $email) {
            $u = User::create(['role_id' => $roles[$role]->id, 'name' => 'Nhân viên demo CN2', 'email' => $email, 'password' => 'Password@123']);
            $u->branches()->attach($branch2->id);
        }
        foreach (['DEMO-A', 'DEMO-B'] as $code) {
            $m = Medicine::create(['code' => $code, 'name' => 'Thuốc mô phỏng '.$code, 'unit' => 'viên', 'strength' => 'Nhãn minh họa', 'price' => 1000]);
            $b = MedicineBatch::create(['branch_id' => $branch->id, 'medicine_id' => $m->id, 'lot_number' => 'LO-DEMO-01', 'expiry_date' => today()->addYear(), 'available_qty' => 100]);
            DB::table('stock_movements')->insert(['batch_id' => $b->id, 'quantity_delta' => 100, 'type' => 'OPENING', 'reference_type' => 'medicine_batches', 'reference_id' => $b->id,
                'actor_id' => User::where('email', 'admin@clinic.test')->value('id'), 'created_at' => now(), 'updated_at' => now()]);
        }

        $patientUser = User::create([
            'role_id' => $roles['patient']->id, 'name' => 'Bệnh nhân Demo',
            'email' => 'benhnhan@clinic.test', 'phone' => '0912000001', 'password' => Hash::make('Password@123'),
        ]);
        $patient = Patient::create([
            'user_id' => $patientUser->id, 'registered_branch_id' => $branch->id, 'patient_code' => 'BN00001', 'full_name' => $patientUser->name,
            'birth_date' => '1995-05-20', 'gender' => 'Nam', 'phone' => $patientUser->phone,
            'email' => $patientUser->email, 'address' => 'Hà Nội',
        ]);

        foreach ($doctors as $doctor) {
            for ($i = 1; $i <= 14; $i++) {
                $date = today()->addDays($i);
                if ($date->isSunday()) {
                    continue;
                }
                $branchId = $doctor->doctor_code === 'BS0003' ? $branch2->id : $branch->id;
                DoctorSchedule::create(['branch_id' => $branchId, 'doctor_id' => $doctor->id, 'work_date' => $date, 'start_time' => '08:00', 'end_time' => '11:30', 'slot_minutes' => 30]);
                DoctorSchedule::create(['branch_id' => $branchId, 'doctor_id' => $doctor->id, 'work_date' => $date, 'start_time' => '13:30', 'end_time' => '17:00', 'slot_minutes' => 30]);
            }
        }

        $schedule = DoctorSchedule::where('doctor_id', $doctors->first()->id)->orderBy('work_date')->first();
        Appointment::create([
            'branch_id' => $branch->id, 'appointment_code' => 'LHDEMO001', 'patient_id' => $patient->id, 'doctor_id' => $doctors->first()->id,
            'doctor_schedule_id' => $schedule->id, 'created_by' => $patientUser->id,
            'appointment_date' => $schedule->work_date, 'start_time' => '08:00', 'end_time' => '08:30',
            'status' => Appointment::PENDING, 'reason' => 'Khám sức khỏe định kỳ',
        ]);
        \App\Models\AuditLog::record('seed.demo_workflows_added', User::where('email', 'admin@clinic.test')->firstOrFail());
    }
}
