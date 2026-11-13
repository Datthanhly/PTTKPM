<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\User;
use App\Models\Visit;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceptionAppointmentActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-13 07:00:00');
        $this->seed(DatabaseSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function loginAs(string $email = 'letan@clinic.test'): User
    {
        $user = User::where('email', $email)->firstOrFail();
        $this->actingAs($user)->withSession(['password_hash_web' => $user->password]);

        return $user;
    }

    private function appointment(bool $today = false): Appointment
    {
        $appointment = Appointment::where('appointment_code', 'LHDEMO001')->firstOrFail();
        if ($today) {
            $appointment->schedule->update(['work_date' => today()]);
            $appointment->update(['appointment_date' => today()]);
        }

        return $appointment->refresh();
    }

    public function test_receptionist_can_confirm_and_check_in_from_dashboard_with_one_visit_only(): void
    {
        $this->loginAs();
        $appointment = $this->appointment(true);
        $this->get(route('dashboard'))->assertOk()->assertSee('Chờ xác nhận')->assertSee('Thao tác')
            ->assertSee('data-appointment-status', false)->assertSee(route('staff.appointments.transition', $appointment));
        $this->from(route('dashboard'))->patch(route('staff.appointments.transition', $appointment), ['status' => 'CONFIRMED'])
            ->assertRedirect(route('dashboard'))->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame('CONFIRMED', $appointment->fresh()->status);
        $this->get(route('dashboard'))->assertOk()->assertSee('Đã xác nhận')->assertSee('Tiếp nhận');
        $this->patchJson(route('staff.appointments.transition', $appointment), ['status' => 'CHECKED_IN'])->assertOk()->assertJson(['message' => 'Đã cập nhật lịch hẹn.']);
        $this->assertSame('CHECKED_IN', $appointment->fresh()->status);
        $this->assertSame(1, Visit::where('appointment_id', $appointment->id)->count());
        $this->patchJson(route('staff.appointments.transition', $appointment), ['status' => 'CHECKED_IN'])->assertConflict();
        $this->assertSame(1, Visit::where('appointment_id', $appointment->id)->count());
        $this->get(route('dashboard'))->assertOk()->assertSee('Đã tiếp nhận');
    }

    public function test_future_check_in_and_early_no_show_are_disabled_and_rejected_with_normal_web_feedback(): void
    {
        $this->loginAs();
        $appointment = $this->appointment();
        $this->patchJson(route('staff.appointments.transition', $appointment), ['status' => 'CONFIRMED'])->assertOk();
        $response = $this->get(route('dashboard'))->assertOk()->assertSee('Tiếp nhận vào ngày '.$appointment->appointment_date->format('d/m/Y'));
        $doc = new \DOMDocument;
        $doc->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($doc);
        $this->assertSame(1, $xpath->query('//form[input[@name="status" and @value="CHECKED_IN"]]/button[@disabled]')->length);
        $this->assertSame(1, $xpath->query('//form[input[@name="status" and @value="NO_SHOW"]]/button[@disabled]')->length);
        $this->from(route('dashboard'))->patch(route('staff.appointments.transition', $appointment), ['status' => 'CHECKED_IN'])
            ->assertRedirect(route('dashboard'))->assertSessionHasErrors('status');
        $this->assertDatabaseCount('visits', 0);
        $this->patchJson(route('staff.appointments.transition', $appointment), ['status' => 'NO_SHOW'])->assertUnprocessable();
        $this->assertSame('CONFIRMED', $appointment->fresh()->status);
        Carbon::setTestNow($appointment->appointment_date->format('Y-m-d').' 08:30:00');
        $this->patchJson(route('staff.appointments.transition', $appointment), ['status' => 'NO_SHOW'])->assertOk();
        $this->assertSame('NO_SHOW', $appointment->fresh()->status);
    }

    public function test_invalid_web_transition_returns_to_form_and_json_retains_conflict_response(): void
    {
        $this->loginAs();
        $appointment = $this->appointment();
        $this->patchJson(route('staff.appointments.transition', $appointment), ['status' => 'CONFIRMED'])->assertOk();
        $this->from(route('dashboard'))->patch(route('staff.appointments.transition', $appointment), ['status' => 'CONFIRMED'])
            ->assertRedirect(route('dashboard'))->assertSessionHasErrors('status');
        $this->patchJson(route('staff.appointments.transition', $appointment), ['status' => 'CONFIRMED'])->assertConflict();
        $this->assertSame('CONFIRMED', $appointment->fresh()->status);
        $this->assertDatabaseCount('visits', 0);
    }

    public function test_receptionist_actions_remain_scoped_to_branch_and_other_roles_cannot_confirm(): void
    {
        $appointment = $this->appointment();
        foreach (['letan.cn2@clinic.test', 'bacsi.an@clinic.test', 'thungan@clinic.test', 'admin@clinic.test', 'benhnhan@clinic.test'] as $email) {
            $this->loginAs($email);
            $this->patchJson(route('staff.appointments.transition', $appointment), ['status' => 'CONFIRMED'])->assertForbidden();
            $this->get(route('dashboard'))->assertOk()->assertDontSee('data-appointment-status', false);
        }
        $this->assertSame('PENDING', $appointment->fresh()->status);
        $this->assertDatabaseCount('visits', 0);
    }

    public function test_staff_account_options_are_grouped_at_sidebar_bottom_and_do_not_remove_patient_navigation(): void
    {
        foreach (['letan@clinic.test', 'bacsi.an@clinic.test', 'thungan@clinic.test', 'admin@clinic.test'] as $email) {
            $user = $this->loginAs($email);
            $response = $this->get(route('home'))->assertOk()->assertDontSee('Đang đăng nhập:');
            $doc = new \DOMDocument;
            $doc->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
            $xpath = new \DOMXPath($doc);
            $this->assertSame(1, $xpath->query('//header/details[@class="sidebar-account"]')->length);
            $this->assertSame(0, $xpath->query('//header/details[@class="sidebar-account" and @open]')->length);
            $this->assertSame(0, $xpath->query('//nav[@id="main-navigation"]//form')->length);
            $this->assertSame(0, $xpath->query('//nav[@id="main-navigation"]//a[contains(@href,"doi-mat-khau") or contains(@href,"dang-nhap-phien-rieng")]')->length);
            $this->assertSame(1, $xpath->query('//details[@class="sidebar-account"]//form[@method="post"]/input[@name="_token"]')->length);
            $this->assertSame(1, $xpath->query('//details[@class="sidebar-account"]//a[@target="_blank" and @rel="noopener"]')->length);
            $this->assertStringContainsString($user->name, $response->getContent());
            $this->get(route('password.change'))->assertOk()->assertSee('aria-current="page"', false);
        }
        $this->loginAs('benhnhan@clinic.test');
        $this->get(route('home'))->assertOk()->assertSee('Đang đăng nhập:')->assertDontSee('class="sidebar-account"', false)->assertSee(route('logout'));
    }
}
