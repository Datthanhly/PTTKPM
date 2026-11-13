<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\User;
use App\Services\AppointmentBookingService;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffCalendarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-12 07:00:00');
        $this->seed(DatabaseSeeder::class);
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

    public function test_doctor_calendar_contains_only_own_shifts_and_no_patient_details_before_a_shift_is_opened(): void
    {
        $doctor = $this->loginAs('bacsi.an@clinic.test')->doctor;
        $response = $this->get(route('staff.appointments', ['date' => '2026-11-12']))
            ->assertOk()->assertSee('Lịch của tôi')->assertSee('Ca sáng')->assertSee('Ca chiều')
            ->assertDontSee('LHDEMO001')->assertDontSee('Bệnh nhân Demo');
        $response->assertViewHas('schedules', fn ($shifts) => $shifts->isNotEmpty() && $shifts->every(fn ($s) => $s->doctor_id === $doctor->id));
        $this->assertSame(1, substr_count($response->getContent(), 'aria-current="page"'));
        $this->get(route('staff.appointments', ['doctor_id' => Doctor::where('doctor_code', 'BS0002')->value('id')]))->assertNotFound();
    }

    public function test_shift_details_load_only_appointments_in_the_selected_shift_and_support_search(): void
    {
        $this->loginAs('bacsi.an@clinic.test');
        $appointment = Appointment::firstOrFail();
        $other = app(AppointmentBookingService::class)->book($appointment->patient, $appointment->doctor, '2026-11-13', '13:30', 'Một ca khác', null, $appointment->branch_id);
        $url = route('staff.shifts.show', $appointment->doctor_schedule_id);
        $response = $this->get($url, ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()
            ->assertSee('LHDEMO001')->assertSee('Bệnh nhân Demo')->assertSee('Khám sức khỏe định kỳ')
            ->assertDontSee($other->appointment_code)->assertDontSee('<html', false);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->get($url.'?q=khong-tim-thay', ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->assertSee('Không có lịch hẹn phù hợp');
        $this->get($url)->assertOk()->assertSee('Về lịch tuần')->assertSee('LHDEMO001');
    }

    public function test_shift_authorization_enforces_doctor_and_branch_boundaries(): void
    {
        $first = Appointment::firstOrFail()->doctor_schedule_id;
        $otherDoctorShift = DoctorSchedule::where('doctor_id', Doctor::where('doctor_code', 'BS0002')->value('id'))->firstOrFail();
        $otherBranchShift = DoctorSchedule::where('doctor_id', Doctor::where('doctor_code', 'BS0003')->value('id'))->firstOrFail();
        $this->loginAs('bacsi.an@clinic.test');
        $this->get(route('staff.shifts.show', $otherDoctorShift))->assertNotFound();
        $this->get(route('staff.shifts.show', $otherDoctorShift), ['X-Requested-With' => 'XMLHttpRequest'])->assertNotFound();
        $this->loginAs('letan@clinic.test');
        $this->get(route('staff.shifts.show', $first))->assertOk()->assertSee('Xác nhận');
        $this->get(route('staff.shifts.show', $otherBranchShift))->assertNotFound();
        $this->loginAs('benhnhan@clinic.test');
        $this->get(route('staff.appointments'))->assertForbidden();
        $this->get(route('staff.shifts.show', $first))->assertForbidden();
    }

    public function test_week_includes_sunday_but_not_the_next_monday(): void
    {
        $doctor = $this->loginAs('bacsi.an@clinic.test')->doctor;
        $branchId = $doctor->user->branches()->firstOrFail()->id;
        $sunday = DoctorSchedule::create([
            'doctor_id' => $doctor->id, 'branch_id' => $branchId, 'work_date' => Carbon::parse('2026-11-15'),
            'start_time' => '08:00', 'end_time' => '11:30', 'slot_minutes' => 30,
        ]);
        $this->get(route('staff.appointments', ['date' => '2026-11-15']))->assertOk()
            ->assertViewHas('schedules', fn ($shifts) => $shifts->contains('id', $sunday->id)
                && $shifts->every(fn ($s) => $s->work_date->betweenIncluded('2026-11-09', '2026-11-15')));
    }

    public function test_cashier_does_not_receive_medical_reason_in_shift_details_and_public_child_pages_highlight_parent_menu(): void
    {
        $this->loginAs('thungan@clinic.test');
        $this->get(route('staff.shifts.show', Appointment::firstOrFail()->doctor_schedule_id))
            ->assertOk()->assertSee('Bệnh nhân Demo')->assertDontSee('Khám sức khỏe định kỳ');
        $response = $this->get(route('doctors.show', Doctor::firstOrFail()))->assertOk();
        $document = new \DOMDocument;
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $links = (new \DOMXPath($document))->query('//header//a[@aria-current="page"]');
        $this->assertSame(1, $links->length);
        $this->assertSame('Bác sĩ', trim($links->item(0)->textContent));
    }
}
