<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_other_authentication_forms_do_not_consume_password_recovery_attempts(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->post('/dang-nhap', ['email' => 'missing@example.test', 'password' => 'Wrong@123'])->assertSessionHasErrors('email');
            $this->post('/dang-ky', [])->assertSessionHasErrors('email');
            $this->post('/dat-lai-mat-khau', [])->assertSessionHasErrors('token');
        }
        $this->post('/quen-mat-khau', ['email' => 'missing@example.test'])->assertRedirect(route('password.request'))->assertSessionHasNoErrors();
    }

    public function test_recovery_limit_keeps_the_form_and_retry_header_and_unlocks_after_a_minute(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->post('/quen-mat-khau', ['email' => 'missing@example.test'])->assertRedirect();
        }
        $response = $this->post('/quen-mat-khau', ['email' => 'missing@example.test', 'password' => 'NeverEchoThisPassword']);
        $response->assertStatus(429)->assertSee('Bạn đã gửi nhiều yêu cầu khôi phục mật khẩu')
            ->assertSee('password-recovery-form', false)->assertSee('missing@example.test')
            ->assertSee('data-retry-after=', false)->assertDontSee('NeverEchoThisPassword');
        $this->assertGreaterThan(0, (int) $response->headers->get('Retry-After'));
        $this->assertLessThanOrEqual(60, (int) $response->headers->get('Retry-After'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->get('/quen-mat-khau')->assertOk();
        $this->travel(61)->seconds();
        $this->post('/quen-mat-khau', ['email' => 'missing@example.test'])->assertRedirect();
    }

    public function test_json_clients_still_receive_429_and_retry_seconds(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->post('/quen-mat-khau', ['email' => 'missing@example.test'])->assertRedirect();
        }
        $response = $this->postJson('/quen-mat-khau', ['email' => 'missing@example.test']);
        $response->assertStatus(429)->assertJsonStructure(['message', 'retry_after']);
        $this->assertSame((int) $response->headers->get('Retry-After'), $response->json('retry_after'));
    }

    public function test_known_unknown_and_repeated_emails_show_the_same_confirmation_and_cooldown(): void
    {
        Notification::fake();
        $user = $this->user();
        $this->post('/quen-mat-khau', ['email' => $user->email])->assertRedirect(route('password.request'));
        $message = session('success');
        $this->get('/quen-mat-khau')->assertOk()->assertSee($user->email)->assertSee('data-retry-after="60"', false);
        $this->post('/quen-mat-khau', ['email' => $user->email])->assertRedirect()->assertSessionHas('success', $message);
        $this->post('/quen-mat-khau', ['email' => 'unknown@example.test'])->assertRedirect()->assertSessionHas('success', $message);
        Notification::assertSentToTimes($user, ResetPassword::class, 1);
        $this->assertDatabaseCount('password_reset_tokens', 1);
        $this->travel(61)->seconds();
        $this->get('/quen-mat-khau')->assertOk()->assertSee('data-retry-after="0"', false);
    }

    public function test_sent_link_can_reset_password_once_and_log_in_using_the_new_password(): void
    {
        Notification::fake();
        $user = $this->user();
        $this->post('/quen-mat-khau', ['email' => $user->email])->assertRedirect();
        $notification = Notification::sent($user, ResetPassword::class)->sole();
        $url = $notification->toMail($user)->actionUrl;
        $this->get($url)->assertOk()->assertSee('Đặt lại mật khẩu')->assertSee($user->email);
        $body = ['email' => $user->email, 'token' => $notification->token, 'password' => 'Changed@123', 'password_confirmation' => 'Changed@123'];
        $this->post('/dat-lai-mat-khau', $body)->assertRedirect(route('login'))->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('Changed@123', $user->fresh()->password));
        $this->assertDatabaseCount('password_reset_tokens', 0);
        $this->post('/dat-lai-mat-khau', $body)->assertSessionHasErrors('email');
        $this->post('/dang-nhap', ['email' => $user->email, 'password' => 'Changed@123'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_reset_email_renders_through_the_mail_transport_without_an_external_delivery(): void
    {
        $user = $this->user();
        $this->post('/quen-mat-khau', ['email' => $user->email])->assertRedirect()->assertSessionHasNoErrors();
        $messages = Mail::mailer()->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $email = $messages->sole()->getOriginalMessage();
        $this->assertSame($user->email, $email->getTo()[0]->getAddress());
        $this->assertStringContainsString('/dat-lai-mat-khau/', $email->getHtmlBody());
    }

    public function test_expired_reset_link_does_not_change_the_password(): void
    {
        Notification::fake();
        $user = $this->user();
        $this->post('/quen-mat-khau', ['email' => $user->email])->assertRedirect();
        $notification = Notification::sent($user, ResetPassword::class)->sole();
        $this->travel(61)->minutes();
        $this->post('/dat-lai-mat-khau', ['email' => $user->email, 'token' => $notification->token, 'password' => 'Changed@123', 'password_confirmation' => 'Changed@123'])
            ->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('Original@123', $user->fresh()->password));
        $this->assertGuest();
    }

    private function user(): User
    {
        $role = Role::create(['code' => 'patient', 'name' => 'Bệnh nhân']);

        return User::create(['role_id' => $role->id, 'name' => 'Khôi phục mật khẩu', 'email' => 'recovery@example.test', 'password' => 'Original@123']);
    }
}
