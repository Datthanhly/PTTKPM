<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
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

    public function test_existing_bcrypt_login_upgrades_hash_and_locked_login_stays_generic(): void
    {
        $role = Role::create(['code' => 'patient', 'name' => 'Bệnh nhân']);
        DB::table('users')->insert(['role_id' => $role->id, 'name' => 'Demo cũ', 'email' => 'legacy@clinic.test', 'password' => password_hash('Password@123', PASSWORD_BCRYPT)]);
        $this->post('/dang-nhap', ['email' => 'legacy@clinic.test', 'password' => 'Password@123'])->assertRedirect('/bang-dieu-khien');
        $this->assertAuthenticated();
        $this->assertSame('argon2id', password_get_info(User::where('email', 'legacy@clinic.test')->value('password'))['algoName']);
        $this->post('/dang-xuat')->assertRedirect('/');
        $this->assertGuest();
        User::where('email', 'legacy@clinic.test')->update(['status' => 'LOCKED']);
        $this->post('/dang-nhap', ['email' => 'legacy@clinic.test', 'password' => 'Password@123'])->assertSessionHasErrors(['email' => 'Email hoặc mật khẩu không đúng.']);
        $this->assertGuest();
    }

    public function test_password_reset_token_is_single_use(): void
    {
        $role = Role::create(['code' => 'patient', 'name' => 'Bệnh nhân']);
        $u = User::create(['role_id' => $role->id, 'name' => 'Reset demo', 'email' => 'reset@clinic.test', 'password' => 'Password@123']);
        $token = Password::createToken($u);
        $body = ['email' => $u->email, 'token' => $token, 'password' => 'Changed@123', 'password_confirmation' => 'Changed@123'];
        $this->post('/dat-lai-mat-khau', $body)->assertRedirect('/dang-nhap');
        $this->assertTrue(Hash::check('Changed@123', $u->fresh()->password));
        $this->assertFalse(Password::tokenExists($u->fresh(), $token));
        $this->post('/dat-lai-mat-khau', $body)->assertSessionHasErrors('email');
    }
}
