<?php

namespace Tests\Feature;

use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_can_register_and_get_a_patient_profile(): void
    {
        Role::create(['code' => 'patient', 'name' => 'Bệnh nhân']);

        $response = $this->post('/dang-ky', [
            'name' => 'Nguyễn Văn A', 'email' => 'a@example.test', 'phone' => '0909123456',
            'birth_date' => '2000-01-01', 'gender' => 'Nam', 'address' => 'Hà Nội',
            'password' => 'Password@123', 'password_confirmation' => 'Password@123',
        ]);

        $response->assertRedirect('/bang-dieu-khien');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('patients', ['full_name' => 'Nguyễn Văn A', 'phone' => '0909123456']);
    }
}
