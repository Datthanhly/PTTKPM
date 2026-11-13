<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AppointmentCutoffTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['clinic.cancellation_hours' => 24]);
        Carbon::setTestNow('2026-11-12 07:00:00');
        $this->seed(DatabaseSeeder::class);
        $patient = User::where('email', 'benhnhan@clinic.test')->firstOrFail();
        $this->actingAs($patient)->withSession(['password_hash_web' => $patient->password]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public static function cutoffCases(): array
    {
        $cases = [];
        foreach (['cancel', 'reschedule'] as $action) {
            $cases[$action.' before cutoff'] = [$action, '2026-11-12 07:59:59', true];
            $cases[$action.' exactly 24 hours'] = [$action, '2026-11-12 08:00:00', true];
            $cases[$action.' under 24 hours by one second'] = [$action, '2026-11-12 08:00:01', false];
            $cases[$action.' after appointment time'] = [$action, '2026-11-13 09:00:00', false];
        }

        return $cases;
    }

    /** @dataProvider cutoffCases */
    public function test_patient_cutoff_redirects_with_a_message_and_preserves_blocked_appointments(string $action, string $now, bool $allowed): void
    {
        $appointment = Appointment::firstOrFail(); // 13/11/2026 at 08:00, Vietnam time.
        $before = $appointment->getRawOriginal();
        $notifications = DB::table('notification_jobs')->count();
        Carbon::setTestNow($now);
        $data = $action === 'cancel' ? ['cancel_reason' => 'Yêu cầu hủy'] : [
            'appointment_date' => '2026-11-13', 'start_time' => '09:30', 'reason' => 'Yêu cầu đổi',
        ];
        $response = $this->from(route('appointments.index'))->patch(route('appointments.'.$action, $appointment), $data);
        $response->assertRedirect(route('appointments.index'));

        if ($allowed) {
            $response->assertSessionHasNoErrors()->assertSessionHas('success');
            if ($action === 'cancel') {
                $this->assertSame('CANCELLED', $appointment->fresh()->status);
                $this->assertSame('Yêu cầu hủy', $appointment->fresh()->cancel_reason);
            } else {
                $this->assertSame('09:30:00', $appointment->fresh()->start_time);
                $this->assertDatabaseHas('appointment_history', ['appointment_id' => $appointment->id, 'reason' => 'Yêu cầu đổi']);
            }
        } else {
            $response->assertSessionHasErrors('appointment')->assertSessionMissing('success');
            $message = $action === 'cancel' ? 'Không thể hủy lịch đăng ký.' : 'Không thể đổi lịch đăng ký.';
            $this->get(route('appointments.index'))->assertOk()->assertSee($message)->assertSee('24 giờ');
            $this->assertSame($before, $appointment->fresh()->getRawOriginal());
            $this->assertDatabaseCount('appointment_history', 0);
            $this->assertSame($notifications, DB::table('notification_jobs')->count());
        }
    }

    public function test_expired_reschedule_page_and_json_requests_show_a_business_message(): void
    {
        Carbon::setTestNow('2026-11-12 08:00:01');
        $appointment = Appointment::firstOrFail();
        $this->get(route('appointments.edit', $appointment))->assertOk()
            ->assertSee('Không thể đổi lịch đăng ký.')->assertDontSee('id="booking-date"', false);
        $this->patchJson(route('appointments.cancel', $appointment), ['cancel_reason' => 'Yêu cầu hủy'])
            ->assertUnprocessable()->assertJsonValidationErrors('appointment');
        $this->patchJson(route('appointments.reschedule', $appointment), [
            'appointment_date' => '2026-11-13', 'start_time' => '09:30', 'reason' => 'Yêu cầu đổi',
        ])->assertUnprocessable()->assertJsonValidationErrors('appointment');
        $this->assertSame('PENDING', $appointment->fresh()->status);
        $this->assertSame('08:00', substr($appointment->fresh()->start_time, 0, 5));
    }
}
