<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\User;
use App\Services\AppointmentBookingService;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DoctorScheduleRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-12 07:00:00');
        $this->seed(DatabaseSeeder::class);
        $this->loginAs('bacsi.an@clinic.test');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function loginAs(string $email): User
    {
        $user = User::where('email', $email)->firstOrFail();
        $this->actingAs($user)->withSession(['password_hash_web' => $user->password]);

        return $user;
    }

    private function input(array $overrides = []): array
    {
        return array_replace([
            'branch_id' => Branch::where('code', 'CN01')->value('id'), 'work_date' => '2026-11-27',
            'session' => 'both', 'slot_minutes' => 30, 'capacity' => 1,
        ], $overrides);
    }

    public function test_doctor_registers_own_morning_and_afternoon_with_weekly_repeat_without_admin_privileges(): void
    {
        $doctor = auth()->user()->doctor;
        $this->get(route('doctor-schedules.create'))->assertRedirect(route('staff.appointments').'#dang-ky-ca');
        $page = $this->get(route('staff.appointments'))->assertOk()->assertSee('Lịch của tôi')->assertSee('id="doctor-schedule-form"', false);
        $document = new \DOMDocument;
        $document->loadHTML($page->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($document);
        $this->assertSame(0, $xpath->query('//form[@id="doctor-schedule-form"]//input[@name="doctor_id"]')->length);
        $this->assertSame(0, $xpath->query('//header//a[contains(text(), "Đăng ký lịch làm")]')->length);
        $this->post(route('doctor-schedules.store'), $this->input([
            'repeat_until' => '2026-12-11', 'doctor_id' => Doctor::where('doctor_code', 'BS0002')->value('id'), 'is_open' => false,
        ]))->assertRedirect(route('staff.appointments', ['date' => '2026-11-27', 'doctor_id' => $doctor->id, 'branch_id' => $this->input()['branch_id']]))->assertSessionHasNoErrors();
        $created = $doctor->schedules()->whereDate('work_date', '>=', '2026-11-27')->get();
        $this->assertCount(6, $created);
        $this->assertTrue($created->every(fn ($s) => $s->is_open));
        $this->assertSame(0, DoctorSchedule::where('doctor_id', Doctor::where('doctor_code', 'BS0002')->value('id'))->whereDate('work_date', '>=', '2026-11-27')->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'schedule.self_registered', 'user_id' => auth()->id()]);
        $this->assertFalse(auth()->user()->hasPermission('SCHEDULE_MANAGE'));
        $this->get(route('staff.appointments', ['date' => '2026-11-27']))->assertOk()
            ->assertViewHas('schedules', fn ($shifts) => $shifts->contains('id', $created->first()->id));
        $this->loginAs('benhnhan@clinic.test');
        $this->getJson(route('appointments.availability', ['doctor_id' => $doctor->id, 'branch_id' => $this->input()['branch_id'], 'date' => '2026-11-27']))
            ->assertOk()->assertJsonCount(14, 'slots');
    }

    public function test_custom_hours_become_available_with_the_selected_duration_and_capacity(): void
    {
        $doctor = auth()->user()->doctor;
        $data = $this->input(['session' => 'custom', 'start_time' => '16:00', 'end_time' => '17:00', 'slot_minutes' => 20, 'capacity' => 2]);
        $this->post(route('doctor-schedules.store'), $data)->assertRedirect()->assertSessionHasNoErrors();
        $slots = app(AppointmentBookingService::class)->availability($doctor, $data['branch_id'], '2026-11-27');
        $this->assertCount(3, $slots);
        $this->assertSame('16:00', $slots[0]['start_time']);
        $this->assertSame('17:00', $slots[2]['end_time']);
        $this->assertSame(2, $slots[0]['remaining']);
    }

    public function test_overlapping_closed_shift_at_another_branch_is_rejected(): void
    {
        $doctor = auth()->user()->doctor;
        $branch2 = Branch::where('code', 'CN02')->firstOrFail();
        $doctor->user->branches()->attach($branch2->id);
        DoctorSchedule::create(['doctor_id' => $doctor->id, 'branch_id' => $branch2->id, 'work_date' => '2026-11-27', 'start_time' => '09:00', 'end_time' => '10:00', 'slot_minutes' => 30, 'is_open' => false]);
        $count = DoctorSchedule::count();
        $this->postJson(route('doctor-schedules.store'), $this->input())->assertUnprocessable()->assertJsonValidationErrors('work_date');
        $this->assertDatabaseCount('doctor_schedules', $count);
    }

    public function test_unassigned_branch_is_rejected(): void
    {
        $this->postJson(route('doctor-schedules.store'), $this->input(['branch_id' => Branch::where('code', 'CN02')->value('id')]))
            ->assertUnprocessable()->assertJsonValidationErrors('branch_id');
    }

    public function test_leave_overlap_is_rejected_but_a_different_session_is_allowed(): void
    {
        DB::table('doctor_time_off')->insert([
            'doctor_id' => auth()->user()->doctor->id, 'starts_at' => '2026-11-27 10:00:00', 'ends_at' => '2026-11-27 12:00:00',
            'reason' => 'Nghỉ có kế hoạch', 'created_by' => User::where('email', 'admin@clinic.test')->value('id'), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->postJson(route('doctor-schedules.store'), $this->input(['session' => 'morning']))->assertUnprocessable()->assertJsonValidationErrors('work_date');
        $this->post(route('doctor-schedules.store'), $this->input(['session' => 'afternoon']))->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_later_repeat_conflict_rolls_back_the_whole_registration(): void
    {
        DoctorSchedule::create(['doctor_id' => auth()->user()->doctor->id, 'branch_id' => $this->input()['branch_id'], 'work_date' => '2026-12-04', 'start_time' => '08:00', 'end_time' => '11:30', 'slot_minutes' => 30]);
        $count = DoctorSchedule::count();
        $this->postJson(route('doctor-schedules.store'), $this->input(['repeat_until' => '2026-12-04']))->assertUnprocessable()->assertJsonValidationErrors('work_date');
        $this->assertDatabaseCount('doctor_schedules', $count);
        $this->assertSame(0, DoctorSchedule::whereDate('work_date', '2026-11-27')->count());
        $this->assertDatabaseMissing('audit_logs', ['action' => 'schedule.self_registered']);
    }

    public static function invalidCases(): array
    {
        return [
            'past session' => [['work_date' => '2026-11-12', 'session' => 'custom', 'start_time' => '06:00', 'end_time' => '06:30'], 'work_date'],
            'too short for one appointment' => [['session' => 'custom', 'start_time' => '09:00', 'end_time' => '09:10'], 'end_time'],
            'reversed time range' => [['session' => 'custom', 'start_time' => '10:00', 'end_time' => '09:00'], 'end_time'],
            'repeat before first date' => [['repeat_until' => '2026-11-26'], 'repeat_until'],
            'date too far in future' => [['work_date' => '2027-06-01'], 'work_date'],
        ];
    }

    /** @dataProvider invalidCases */
    public function test_invalid_registration_does_not_create_shifts(array $overrides, string $field): void
    {
        $count = DoctorSchedule::count();
        $this->postJson(route('doctor-schedules.store'), $this->input($overrides))->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertDatabaseCount('doctor_schedules', $count);
    }

    public function test_other_roles_cannot_register_doctor_shifts(): void
    {
        foreach (['benhnhan@clinic.test', 'letan@clinic.test', 'thungan@clinic.test', 'admin@clinic.test'] as $email) {
            $this->loginAs($email);
            $this->get(route('doctor-schedules.create'))->assertForbidden();
            $this->post(route('doctor-schedules.store'), $this->input())->assertForbidden();
        }
    }

    public function test_rejected_registration_reopens_the_form_inside_my_calendar(): void
    {
        $calendar = route('staff.appointments');
        $this->from($calendar)->post(route('doctor-schedules.store'), $this->input(['work_date' => '2026-11-13']))
            ->assertRedirect($calendar)->assertSessionHasErrors('work_date', null, 'doctorSchedule');
        $page = $this->get($calendar)->assertOk()->assertSee('Trùng ca làm của bác sĩ');
        $document = new \DOMDocument;
        $document->loadHTML($page->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $this->assertSame(1, (new \DOMXPath($document))->query('//details[@id="dang-ky-ca"][@open]')->length);
    }
}
