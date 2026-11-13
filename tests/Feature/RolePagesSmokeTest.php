<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePagesSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_navigation_matches_roles_and_keeps_writes_protected(): void
    {
        $this->seed(DatabaseSeeder::class);
        $doctor = Doctor::firstOrFail();

        $this->get('/lich-kham/dat-lich')->assertRedirect(route('login'));

        foreach (['admin@clinic.test', 'bacsi.an@clinic.test', 'thungan@clinic.test'] as $email) {
            $user = User::where('email', $email)->firstOrFail();
            $this->actingAs($user)->withSession(['password_hash_web' => $user->password]);
            $this->get('/')->assertOk()->assertDontSee(route('appointments.create'), false);
            $this->get(route('doctors.show', $doctor))->assertOk()->assertDontSee(route('appointments.create'), false);
            $this->get('/lich-kham/dat-lich')->assertRedirect(route('dashboard'))
                ->assertSessionHas('info');
            $this->get('/bang-dieu-khien')->assertOk()
                ->assertSee('Đặt lịch khám dành cho tài khoản Bệnh nhân hoặc Lễ tân.');
            $this->post('/lich-kham', [])->assertForbidden();
            $this->get('/lich-kham/kha-dung')->assertForbidden();
        }

        foreach (['benhnhan@clinic.test', 'letan@clinic.test'] as $email) {
            $user = User::where('email', $email)->firstOrFail();
            $this->actingAs($user)->withSession(['password_hash_web' => $user->password]);
            $this->get('/')->assertOk()->assertSee(route('appointments.create'), false);
            $this->get(route('doctors.show', $doctor))->assertOk()->assertSee(route('appointments.create'), false);
            $this->get('/lich-kham/dat-lich')->assertOk()->assertSee('name="doctor_id"', false);
        }
    }

    public function test_public_and_role_pages_render_with_seed_data(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->get('/')->assertOk()->assertSee('Phòng khám An Tâm');

        $patient = User::where('email', 'benhnhan@clinic.test')->firstOrFail();
        $this->actingAs($patient)->get('/lich-kham')->assertOk();
        $this->actingAs($patient)->get('/lich-kham/dat-lich')->assertOk();

        $receptionist = User::where('email', 'letan@clinic.test')->firstOrFail();
        $this->actingAs($receptionist)->withSession(['password_hash_web' => $receptionist->password])->get('/nhan-vien/lich-hen')->assertOk();

        $doctor = User::where('email', 'bacsi.an@clinic.test')->firstOrFail();
        $this->actingAs($doctor)->withSession(['password_hash_web' => $doctor->password])->get('/nhan-vien/lich-hen')->assertOk();

        $admin = User::where('email', 'admin@clinic.test')->firstOrFail();
        $this->actingAs($admin)->withSession(['password_hash_web' => $admin->password])->get('/quan-tri/danh-muc')->assertOk();
    }
}
