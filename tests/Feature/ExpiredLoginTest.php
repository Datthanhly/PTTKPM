<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpiredLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Exercise actual CSRF checks, which Laravel normally skips during unit tests.
        $this->app->bind(VerifyCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends VerifyCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
    }

    public function test_stale_login_returns_a_fresh_form_without_replaying_or_storing_the_password(): void
    {
        $role = Role::create(['code' => 'patient', 'name' => 'Bệnh nhân']);
        User::create(['role_id' => $role->id, 'name' => 'Demo', 'email' => 'expired@clinic.test', 'password' => 'Password@123']);
        $this->withSession(['_token' => 'current-token'])->post('/dang-nhap', [
            '_token' => 'stale-token', 'email' => 'expired@clinic.test', 'password' => 'Password@123', 'remember' => 1,
        ])->assertStatus(303)->assertRedirect(route('login'))
            ->assertSessionHasInput('email', 'expired@clinic.test')
            ->assertSessionMissing('_old_input.password')->assertSessionMissing('_old_input._token');
        $this->assertGuest();
        $this->assertDatabaseMissing('audit_logs', ['action' => 'auth.login']);
        $response = $this->get('/dang-nhap')->assertOk()->assertSee('Vui lòng nhập lại mật khẩu')
            ->assertSee('expired@clinic.test')->assertDontSee('Password@123');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->post('/dang-nhap', [
            '_token' => 'current-token', 'email' => 'expired@clinic.test', 'password' => 'Password@123',
        ])->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
    }

    public function test_json_login_with_an_invalid_token_still_returns_419(): void
    {
        $this->withSession(['_token' => 'current-token'])->postJson('/dang-nhap', [
            '_token' => 'stale-token', 'email' => 'expired@clinic.test', 'password' => 'Password@123',
        ])->assertStatus(419)->assertJsonStructure(['message']);
        $this->assertGuest();
    }

    public function test_other_forms_with_an_invalid_token_still_reject_the_operation(): void
    {
        $this->withSession(['_token' => 'current-token'])->post('/lich-kham', [
            '_token' => 'stale-token', 'reason' => 'Không được lưu',
        ])->assertStatus(419)->assertSee('Thao tác vừa gửi chưa được thực hiện.');
        $this->assertDatabaseCount('appointments', 0);
    }
}
