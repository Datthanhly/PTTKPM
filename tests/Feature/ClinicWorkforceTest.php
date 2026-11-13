<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Role;
use App\Models\Specialty;
use App\Models\User;
use App\Services\AppointmentBookingService;
use Carbon\Carbon;
use Database\Seeders\ClinicWorkforceSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ClinicWorkforceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-12 07:00:00');
        $this->seed(DatabaseSeeder::class);
        Branch::create(['code' => '003', 'name' => 'Phòng khám An Tâm 3']);
        // Match an existing installation where all three original doctors belong to CN01.
        foreach (Doctor::all() as $doctor) {
            $doctor->user->branches()->sync([Branch::where('code', 'CN01')->value('id')]);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_missing_workforce_is_balanced_and_bookable_without_changing_existing_data_or_repeating(): void
    {
        $original = User::where('email', 'bacsi.an@clinic.test')->firstOrFail();
        $original->update(['password' => 'PrivateChanged@123', 'name' => 'Bác sĩ đã đổi tên']);
        $oldUsers = DB::table('users')->orderBy('id')->get()->toArray();
        $oldDoctors = DB::table('doctors')->orderBy('id')->get()->toArray();
        $oldBranches = DB::table('branch_user')->orderBy('user_id')->orderBy('branch_id')->get()->toArray();
        $oldShifts = DB::table('doctor_schedules')->orderBy('id')->get()->toArray();
        $oldAppointments = Appointment::orderBy('id')->get()->toArray();
        $inactive = Branch::create(['code' => 'CLOSED', 'name' => 'Cơ sở đóng', 'is_active' => false]);
        $inactiveSpecialty = Specialty::create(['code' => 'CLOSED', 'name' => 'Chuyên khoa đóng', 'is_active' => false]);

        $seeder = app(ClinicWorkforceSeeder::class);
        $seeder->run();
        $this->assertCount(30, $seeder->createdDoctorIds);
        $this->assertCount(8, $seeder->createdStaffIds);
        $this->assertSame(720, $seeder->createdSchedules);
        foreach (Branch::where('is_active', true)->get() as $branch) {
            $this->assertSame(11, $branch->users()->whereHas('doctor')->count());
            foreach (['receptionist', 'cashier'] as $role) {
                $this->assertSame(2, $branch->users()->whereHas('role', fn ($q) => $q->where('code', $role))->count());
            }
            foreach (Specialty::where('is_active', true)->get() as $specialty) {
                $this->assertSame(1, Doctor::where('specialty_id', $specialty->id)->whereHas('user.branches', fn ($q) => $q->where('branches.id', $branch->id))->count());
            }
        }
        $this->assertSame(0, $inactive->users()->count());
        $this->assertSame(0, Doctor::where('specialty_id', $inactiveSpecialty->id)->count());
        $newDoctor = Doctor::findOrFail($seeder->createdDoctorIds[0]);
        $branchId = $newDoctor->user->branches()->value('branches.id');
        $this->assertTrue(Hash::check('Password@123', $newDoctor->user->password));
        $this->assertSame(14, count(app(AppointmentBookingService::class)->availability($newDoctor, $branchId, '2026-11-13')));
        $this->assertDatabaseMissing('user_permissions', ['user_id' => $newDoctor->user_id]);
        $this->assertEquals($oldUsers, DB::table('users')->whereIn('id', array_column($oldUsers, 'id'))->orderBy('id')->get()->toArray());
        $this->assertEquals($oldDoctors, DB::table('doctors')->whereIn('id', array_column($oldDoctors, 'id'))->orderBy('id')->get()->toArray());
        $this->assertEquals($oldBranches, DB::table('branch_user')->whereIn('user_id', array_column($oldUsers, 'id'))->orderBy('user_id')->orderBy('branch_id')->get()->toArray());
        $this->assertEquals($oldShifts, DB::table('doctor_schedules')->whereIn('id', array_column($oldShifts, 'id'))->orderBy('id')->get()->toArray());
        $this->assertSame($oldAppointments, Appointment::orderBy('id')->get()->toArray());
        $counts = [User::count(), Doctor::count(), DoctorSchedule::count(), DB::table('branch_user')->count()];
        $seeder->run();
        $this->assertSame([], $seeder->createdDoctorIds);
        $this->assertSame([], $seeder->createdStaffIds);
        $this->assertSame(0, $seeder->createdSchedules);
        $this->assertSame($counts, [User::count(), Doctor::count(), DoctorSchedule::count(), DB::table('branch_user')->count()]);
    }

    public function test_email_collision_does_not_claim_an_existing_patient_and_console_command_is_available(): void
    {
        $patient = User::create(['role_id' => Role::where('code', 'patient')->value('id'), 'name' => 'Giữ tài khoản bệnh nhân', 'email' => 'bacsi.tm.cn02@clinic.test', 'password' => 'Private@123']);
        $this->artisan('clinic:staff')->assertSuccessful();
        $this->assertTrue($patient->fresh()->hasRole('patient'));
        $this->assertTrue(Hash::check('Private@123', $patient->fresh()->password));
        $this->assertSame(0, $patient->branches()->count());
        $new = User::where('email', 'bacsi.tm.cn02.2@clinic.test')->firstOrFail();
        $this->assertTrue($new->hasRole('doctor'));
        $this->assertSame(['CN02'], $new->branches()->pluck('code')->all());
        $staff = User::where('email', 'letan.003.1@clinic.test')->firstOrFail();
        $this->assertSame(['003'], $staff->branches()->pluck('code')->all());
        $this->actingAs($staff)->withSession(['password_hash_web' => $staff->password])->get(route('admin.settings'))->assertForbidden();
    }
}
