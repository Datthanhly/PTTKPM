<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\NotificationJob;
use App\Models\ShiftTransferRequest;
use App\Models\User;
use App\Models\Visit;
use App\Services\AppointmentBookingService;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ShiftTransferTest extends TestCase
{
    use RefreshDatabase;

    private DoctorSchedule $shift;

    private Doctor $sender;

    private Doctor $receiver;

    private Appointment $appointment;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-12 07:00:00');
        $this->seed(DatabaseSeeder::class);
        $this->sender = Doctor::where('doctor_code', 'BS0001')->firstOrFail();
        $this->receiver = Doctor::where('doctor_code', 'BS0002')->firstOrFail();
        $this->receiver->update(['specialty_id' => $this->sender->specialty_id]);
        $this->receiver->specialties()->sync([$this->sender->specialty_id]);
        $this->shift = DoctorSchedule::create([
            'doctor_id' => $this->sender->id, 'branch_id' => Branch::where('code', 'CN01')->value('id'),
            'work_date' => '2026-11-27', 'start_time' => '08:00', 'end_time' => '11:30', 'slot_minutes' => 30, 'capacity' => 1,
        ]);
        $patient = User::where('email', 'benhnhan@clinic.test')->firstOrFail()->patient;
        $patient->update(['email_enabled' => true]);
        $this->appointment = app(AppointmentBookingService::class)->book($patient, $this->sender, '2026-11-27', '08:00', 'Khám theo hẹn', $patient->user, $this->shift->branch_id);
        $this->loginAs('bacsi.an@clinic.test');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function loginAs(string $email): void
    {
        $user = User::where('email', $email)->firstOrFail();
        $this->actingAs($user)->withSession(['password_hash_web' => $user->password]);
    }

    private function send(): ShiftTransferRequest
    {
        $this->postJson(route('doctor-shifts.transfer', $this->shift), ['receiver_doctor_id' => $this->receiver->id, 'note' => 'Nhờ hỗ trợ ca sáng'])
            ->assertOk();

        return ShiftTransferRequest::latest('id')->firstOrFail();
    }

    public function test_eligible_doctor_receives_notification_and_acceptance_transfers_shift_and_appointments_once(): void
    {
        $this->get(route('staff.shifts.show', $this->shift))->assertOk()->assertSee('BS. Trần Thu Hà');
        $transfer = $this->send();
        $this->assertSame($this->sender->id, $this->shift->fresh()->doctor_id);
        $this->assertSame($this->sender->id, $this->appointment->fresh()->doctor_id);
        $this->loginAs('bacsi.ha@clinic.test');
        $this->get(route('dashboard'))->assertOk()->assertSee('Thông báo')->assertSee('Nhờ hỗ trợ ca sáng')->assertDontSee('Khám theo hẹn');
        $this->patch(route('doctor-shifts.respond', $transfer), ['decision' => 'accept'])->assertRedirect(route('dashboard'))->assertSessionHasNoErrors();
        $this->assertSame('ACCEPTED', $transfer->fresh()->status);
        $this->assertNull($transfer->fresh()->pending_key);
        $this->assertSame($this->receiver->id, $this->shift->fresh()->doctor_id);
        $this->assertSame($this->receiver->id, $this->appointment->fresh()->doctor_id);
        $this->assertSame('08:00', substr($this->appointment->fresh()->start_time, 0, 5));
        $this->assertDatabaseHas('appointment_history', ['appointment_id' => $this->appointment->id, 'changed_by' => $this->receiver->user_id]);
        $notification = NotificationJob::where('template', 'DOCTOR_CHANGED_'.$transfer->id)->firstOrFail();
        $this->assertStringContainsString('BS. Trần Thu Hà', $notification->body);
        $this->get(route('staff.shifts.show', $this->shift))->assertOk()->assertSee($this->appointment->appointment_code);
        $this->patchJson(route('doctor-shifts.respond', $transfer), ['decision' => 'accept'])->assertUnprocessable();
        $this->assertSame(1, DB::table('appointment_history')->where('appointment_id', $this->appointment->id)->count());
        $this->loginAs('bacsi.an@clinic.test');
        $this->get(route('staff.shifts.show', $this->shift))->assertNotFound();
    }

    public function test_ownership_roles_and_notification_privacy_are_enforced(): void
    {
        $transfer = $this->send();
        $this->patchJson(route('doctor-shifts.respond', $transfer), ['decision' => 'accept'])->assertForbidden();
        $this->loginAs('bacsi.nam@clinic.test');
        $this->get(route('dashboard'))->assertOk()->assertDontSee('Nhờ hỗ trợ ca sáng');
        $this->patchJson(route('doctor-shifts.respond', $transfer), ['decision' => 'accept'])->assertForbidden();
        $this->postJson(route('doctor-shifts.transfer', $this->shift), ['receiver_doctor_id' => $this->receiver->id, 'note' => 'Không phải ca của tôi'])->assertForbidden();
        $this->loginAs('bacsi.ha@clinic.test');
        $this->patchJson(route('doctor-shifts.respond', $transfer), ['decision' => 'cancel'])->assertForbidden();
        $this->loginAs('benhnhan@clinic.test');
        $this->patchJson(route('doctor-shifts.respond', $transfer), ['decision' => 'accept'])->assertForbidden();
    }

    public static function unavailableCases(): array
    {
        return [['specialty'], ['branch'], ['inactive'], ['overlap'], ['leave'], ['visit']];
    }

    /** @dataProvider unavailableCases */
    public function test_unavailable_receivers_are_not_offered_and_cannot_be_requested(string $case): void
    {
        if ($case === 'specialty') {
            $this->receiver->update(['specialty_id' => Doctor::where('doctor_code', 'BS0003')->value('specialty_id')]);
            $this->receiver->specialties()->detach();
        } elseif ($case === 'branch') {
            $this->receiver->user->branches()->detach();
        } elseif ($case === 'inactive') {
            $this->receiver->user->update(['status' => 'LOCKED']);
        } elseif ($case === 'overlap') {
            $branch2 = Branch::where('code', 'CN02')->firstOrFail();
            $this->receiver->user->branches()->attach($branch2->id);
            DoctorSchedule::create(['doctor_id' => $this->receiver->id, 'branch_id' => $branch2->id, 'work_date' => '2026-11-27', 'start_time' => '09:00', 'end_time' => '10:00', 'slot_minutes' => 30, 'is_open' => false]);
        } elseif ($case === 'leave') {
            DB::table('doctor_time_off')->insert(['doctor_id' => $this->receiver->id, 'starts_at' => '2026-11-27 09:00:00', 'ends_at' => '2026-11-27 10:00:00', 'reason' => 'Nghỉ', 'created_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
        } else {
            Visit::create(['visit_code' => 'BUSY-RECEIVER', 'patient_id' => $this->appointment->patient_id, 'doctor_id' => $this->receiver->id, 'branch_id' => $this->shift->branch_id, 'status' => 'WAITING', 'reason' => 'Đang chờ khám', 'checked_in_at' => '2026-11-27 07:00:00']);
        }
        $this->get(route('staff.shifts.show', $this->shift))->assertOk()->assertDontSee('value="'.$this->receiver->id.'"', false);
        $this->postJson(route('doctor-shifts.transfer', $this->shift), ['receiver_doctor_id' => $this->receiver->id, 'note' => 'Đổi ca'])->assertUnprocessable()->assertJsonValidationErrors('transfer');
        $this->assertDatabaseCount('shift_transfer_requests', 0);
    }

    public function test_new_conflict_between_request_and_acceptance_is_rechecked_without_partial_updates(): void
    {
        $transfer = $this->send();
        DoctorSchedule::create(['doctor_id' => $this->receiver->id, 'branch_id' => $this->shift->branch_id, 'work_date' => '2026-11-27', 'start_time' => '10:00', 'end_time' => '11:30', 'slot_minutes' => 30]);
        $this->loginAs('bacsi.ha@clinic.test');
        $this->patchJson(route('doctor-shifts.respond', $transfer), ['decision' => 'accept'])->assertUnprocessable()->assertJsonValidationErrors('transfer');
        $this->assertSame('PENDING', $transfer->fresh()->status);
        $this->assertSame($this->sender->id, $this->shift->fresh()->doctor_id);
        $this->assertSame($this->sender->id, $this->appointment->fresh()->doctor_id);
        $this->assertDatabaseCount('appointment_history', 0);
    }

    public function test_checked_in_patient_prevents_acceptance_and_expired_requests_cannot_be_accepted(): void
    {
        $transfer = $this->send();
        $this->appointment->update(['status' => 'CHECKED_IN']);
        $this->loginAs('bacsi.ha@clinic.test');
        $this->patchJson(route('doctor-shifts.respond', $transfer), ['decision' => 'accept'])->assertUnprocessable();
        $this->appointment->update(['status' => 'PENDING']);
        Carbon::setTestNow('2026-11-27 08:00:00');
        $this->patchJson(route('doctor-shifts.respond', $transfer), ['decision' => 'accept'])->assertUnprocessable();
        $this->assertSame($this->sender->id, $this->shift->fresh()->doctor_id);
    }

    public function test_pending_duplicates_are_rejected_and_rejection_or_withdrawal_keeps_appointments_unchanged(): void
    {
        $first = $this->send();
        $this->postJson(route('doctor-shifts.transfer', $this->shift), ['receiver_doctor_id' => $this->receiver->id, 'note' => 'Gửi lại'])->assertUnprocessable();
        $this->patch(route('doctor-shifts.respond', $first), ['decision' => 'cancel'])->assertRedirect();
        $this->assertSame('CANCELLED', $first->fresh()->status);
        $second = $this->send();
        $this->loginAs('bacsi.ha@clinic.test');
        $this->patch(route('doctor-shifts.respond', $second), ['decision' => 'reject'])->assertRedirect();
        $this->assertSame('REJECTED', $second->fresh()->status);
        $this->assertSame($this->sender->id, $this->shift->fresh()->doctor_id);
        $this->assertSame($this->sender->id, $this->appointment->fresh()->doctor_id);
        $this->assertDatabaseCount('appointment_history', 0);
    }
}
