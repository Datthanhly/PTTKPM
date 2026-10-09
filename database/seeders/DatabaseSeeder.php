<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Patient;
use App\Models\Role;
use App\Models\Service;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $roles = collect([
            'patient' => 'Bệnh nhân', 'receptionist' => 'Lễ tân', 'doctor' => 'Bác sĩ',
            'cashier' => 'Thu ngân', 'admin' => 'Quản trị viên',
        ])->mapWithKeys(fn ($name, $code) => [$code => Role::create(compact('code', 'name'))]);

        foreach ([
            ['TM', 'Tim mạch', 'Khám, tư vấn và theo dõi bệnh lý tim mạch.'],
            ['NOI', 'Nội tổng quát', 'Khám sức khỏe và bệnh lý nội khoa thông thường.'],
            ['NHI', 'Nhi khoa', 'Chăm sóc sức khỏe trẻ em.'],
        ] as [$code, $name, $description]) Specialty::create(compact('code', 'name', 'description'));

        foreach ([
            ['DV001', 'Khám tổng quát', 200000], ['DV002', 'Điện tâm đồ', 180000],
            ['DV003', 'Siêu âm', 300000], ['DV004', 'Xét nghiệm máu cơ bản', 250000],
        ] as [$code, $name, $price]) Service::create(compact('code', 'name', 'price'));

        $staff = [
            ['admin', 'Quản trị hệ thống', 'admin@clinic.test', '0900000001'],
            ['receptionist', 'Lễ tân Demo', 'letan@clinic.test', '0900000002'],
            ['cashier', 'Thu ngân Demo', 'thungan@clinic.test', '0900000003'],
        ];
        foreach ($staff as [$role, $name, $email, $phone]) {
            User::create(['role_id' => $roles[$role]->id, 'name' => $name, 'email' => $email, 'phone' => $phone, 'password' => Hash::make('Password@123')]);
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
        }

        $patientUser = User::create([
            'role_id' => $roles['patient']->id, 'name' => 'Bệnh nhân Demo',
            'email' => 'benhnhan@clinic.test', 'phone' => '0912000001', 'password' => Hash::make('Password@123'),
        ]);
        $patient = Patient::create([
            'user_id' => $patientUser->id, 'patient_code' => 'BN00001', 'full_name' => $patientUser->name,
            'birth_date' => '1995-05-20', 'gender' => 'Nam', 'phone' => $patientUser->phone,
            'email' => $patientUser->email, 'address' => 'Hà Nội',
        ]);

        foreach ($doctors as $doctor) {
            for ($i = 1; $i <= 14; $i++) {
                $date = today()->addDays($i);
                if ($date->isSunday()) continue;
                DoctorSchedule::create(['doctor_id' => $doctor->id, 'work_date' => $date, 'start_time' => '08:00', 'end_time' => '11:30', 'slot_minutes' => 30]);
                DoctorSchedule::create(['doctor_id' => $doctor->id, 'work_date' => $date, 'start_time' => '13:30', 'end_time' => '17:00', 'slot_minutes' => 30]);
            }
        }

        $schedule = DoctorSchedule::where('doctor_id', $doctors->first()->id)->orderBy('work_date')->first();
        Appointment::create([
            'appointment_code' => 'LHDEMO001', 'patient_id' => $patient->id, 'doctor_id' => $doctors->first()->id,
            'doctor_schedule_id' => $schedule->id, 'created_by' => $patientUser->id,
            'appointment_date' => $schedule->work_date, 'start_time' => '08:00', 'end_time' => '08:30',
            'status' => Appointment::PENDING, 'reason' => 'Khám sức khỏe định kỳ',
        ]);
    }
}
