<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Patient;
use App\Models\Role;
use App\Models\Specialty;
use App\Models\User;
use App\Services\AppointmentBookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AppointmentBookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_rejects_an_overlapping_active_appointment(): void
    {
        $patientRole = Role::create(['code' => 'patient', 'name' => 'Bệnh nhân']);
        $doctorRole = Role::create(['code' => 'doctor', 'name' => 'Bác sĩ']);
        $specialty = Specialty::create(['code' => 'NOI', 'name' => 'Nội']);
        $doctorUser = User::create(['role_id' => $doctorRole->id, 'name' => 'Bác sĩ A', 'email' => 'doctor@test.local', 'password' => 'Password@123']);
        $doctor = Doctor::create(['user_id' => $doctorUser->id, 'specialty_id' => $specialty->id, 'doctor_code' => 'BS01', 'license_number' => 'GP01']);
        $date = today()->addDays(2)->format('Y-m-d');
        DoctorSchedule::create(['doctor_id' => $doctor->id, 'work_date' => $date, 'start_time' => '08:00', 'end_time' => '11:00', 'slot_minutes' => 30]);
        $makePatient = function (string $email, string $code) use ($patientRole) {
            $user = User::create(['role_id' => $patientRole->id, 'name' => $code, 'email' => $email, 'password' => 'Password@123']);
            return Patient::create(['user_id' => $user->id, 'patient_code' => $code, 'full_name' => $code]);
        };
        $service = app(AppointmentBookingService::class);
        $service->book($makePatient('p1@test.local', 'BN01'), $doctor, $date, '08:00', 'Khám lần 1');

        $this->expectException(ValidationException::class);
        $service->book($makePatient('p2@test.local', 'BN02'), $doctor, $date, '08:00', 'Khám lần 2');
    }

    public function test_booking_must_follow_the_schedule_slot_step(): void
    {
        $patientRole = Role::create(['code' => 'patient', 'name' => 'Bệnh nhân']);
        $doctorRole = Role::create(['code' => 'doctor', 'name' => 'Bác sĩ']);
        $specialty = Specialty::create(['code' => 'TIM', 'name' => 'Tim']);
        $doctorUser = User::create(['role_id' => $doctorRole->id, 'name' => 'Bác sĩ B', 'email' => 'doctor2@test.local', 'password' => 'Password@123']);
        $doctor = Doctor::create(['user_id' => $doctorUser->id, 'specialty_id' => $specialty->id, 'doctor_code' => 'BS02', 'license_number' => 'GP02']);
        $date = today()->addDays(2)->format('Y-m-d');
        DoctorSchedule::create(['doctor_id' => $doctor->id, 'work_date' => $date, 'start_time' => '08:00', 'end_time' => '11:00', 'slot_minutes' => 30]);
        $user = User::create(['role_id' => $patientRole->id, 'name' => 'BN', 'email' => 'p@test.local', 'password' => 'Password@123']);
        $patient = Patient::create(['user_id' => $user->id, 'patient_code' => 'BN03', 'full_name' => 'BN']);

        $service = app(AppointmentBookingService::class);
        $lastSlot = $service->book($patient, $doctor, $date, '10:30', 'Khung cuối ca');
        $this->assertSame('10:30:00', $lastSlot->start_time);

        $this->expectException(ValidationException::class);
        $service->book($patient, $doctor, $date, '08:10', 'Sai bước giờ');
    }
}
