<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePagesSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_and_role_pages_render_with_seed_data(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->get('/')->assertOk()->assertSee('Phòng khám An Tâm');

        $patient = User::where('email', 'benhnhan@clinic.test')->firstOrFail();
        $this->actingAs($patient)->get('/lich-kham')->assertOk();
        $this->actingAs($patient)->get('/lich-kham/dat-lich')->assertOk();

        $receptionist = User::where('email', 'letan@clinic.test')->firstOrFail();
        $this->actingAs($receptionist)->get('/nhan-vien/lich-hen')->assertOk();

        $doctor = User::where('email', 'bacsi.an@clinic.test')->firstOrFail();
        $this->actingAs($doctor)->get('/nhan-vien/lich-hen')->assertOk();

        $admin = User::where('email', 'admin@clinic.test')->firstOrFail();
        $this->actingAs($admin)->get('/quan-tri/danh-muc')->assertOk();
    }
}
