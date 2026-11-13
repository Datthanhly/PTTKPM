<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Specialty;
use App\Models\User;
use Database\Seeders\ClinicSpecialtySeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClinicSpecialtyUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_specialty_update_preserves_existing_data_when_repeated(): void
    {
        $this->seed(DatabaseSeeder::class);
        $specialty = Specialty::where('code', 'NOI')->firstOrFail();
        $specialty->update(['name' => 'Nội tổng quát']);
        $custom = Specialty::create(['code' => 'CUSTOM', 'name' => 'Chuyên khoa bổ sung', 'is_active' => false]);
        $assignments = Doctor::orderBy('id')->pluck('specialty_id', 'id')->all();
        $appointments = Appointment::count();
        $users = User::count();
        $count = Specialty::count();

        $this->seed(ClinicSpecialtySeeder::class);
        $this->seed(ClinicSpecialtySeeder::class);

        $this->assertSame($count, Specialty::count());
        $this->assertSame('Nội khoa', $specialty->fresh()->name);
        $this->assertFalse($custom->fresh()->is_active);
        $this->assertSame($assignments, Doctor::orderBy('id')->pluck('specialty_id', 'id')->all());
        $this->assertSame($appointments, Appointment::count());
        $this->assertSame($users, User::count());
        $this->get('/')->assertOk()->assertSee('Khám chuyên khoa')->assertSee('Tầm soát sức khỏe')
            ->assertSee('Chẩn đoán hình ảnh &amp; Xét nghiệm', false)
            ->assertSee('Tư vấn sức khỏe tâm thần &amp; Tâm lý', false)
            ->assertDontSee('Chuyên khoa bổ sung');
    }
}
