<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\DoctorSchedule;
use App\Models\Role;
use App\Models\StaffScheduleRequest;
use App\Models\StaffWorkShift;
use App\Models\User;
use App\Services\StaffRosterService;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffRosterTest extends TestCase
{
    use RefreshDatabase;

    private int $branch;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-12 07:00:00');
        $this->seed(DatabaseSeeder::class);
        $this->branch = Branch::where('code', 'CN01')->value('id');
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

    private function payload(array $changes = []): array
    {
        return array_replace(['branch_id' => $this->branch, 'from_date' => '2026-11-16', 'to_date' => '2026-11-16', 'weekdays' => [1, 2, 3, 4, 5, 6], 'session' => 'both', 'note' => 'Có thể làm theo đăng ký'], $changes);
    }

    private function submit(string $email = 'letan@clinic.test', array $changes = []): void
    {
        $this->loginAs($email);
        $this->post(route('employee-schedules.store'), $this->payload($changes))->assertRedirect()->assertSessionHasNoErrors();
    }

    private function manual(StaffScheduleRequest $registration, array $changes = []): StaffWorkShift
    {
        $this->loginAs('admin@clinic.test');
        $data = array_replace(['branch_id' => $registration->branch_id, 'work_date' => $registration->work_date->format('Y-m-d'),
            'start_time' => substr($registration->start_time, 0, 5), 'end_time' => substr($registration->end_time, 0, 5), 'note' => 'Admin xếp bằng tay', 'plan_version' => $registration->shift?->version() ?? 'new'], $changes);
        $this->post(route('admin.roster.arrange', $registration), $data)->assertRedirect()->assertSessionHasNoErrors();

        return StaffWorkShift::where('request_id', $registration->id)->firstOrFail();
    }

    private function version(): string
    {
        return app(StaffRosterService::class)->fingerprint(StaffWorkShift::where('status', 'DRAFT')->get());
    }

    public function test_registration_is_waiting_owned_by_the_employee_and_not_an_official_shift(): void
    {
        $user = $this->loginAs('letan@clinic.test');
        $this->post(route('employee-schedules.store'), $this->payload(['user_id' => User::where('email', 'admin@clinic.test')->value('id'), 'status' => 'CONFIRMED']))
            ->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseCount('staff_schedule_requests', 2);
        $this->assertDatabaseCount('staff_work_shifts', 0);
        $this->assertTrue(StaffScheduleRequest::get()->every(fn ($r) => $r->user_id === $user->id && $r->status === 'WAITING'));
        $this->get(route('employee-schedules.index', ['date' => '2026-11-16']))->assertOk()->assertSee('WAITING')
            ->assertViewHas('shifts', fn ($s) => $s->isEmpty())->assertViewHas('registrations', fn ($r) => $r->total() === 2);
        $registration = StaffScheduleRequest::firstOrFail();
        $this->loginAs('thungan@clinic.test');
        $this->get(route('employee-schedules.index', ['date' => '2026-11-16']))->assertOk()->assertViewHas('registrations', fn ($r) => $r->total() === 0);
        $this->patchJson(route('employee-schedules.withdraw', $registration))->assertForbidden();
        $this->postJson(route('admin.roster.auto'), ['date' => '2026-11-16', 'receptionist_count' => 1, 'cashier_count' => 1])->assertForbidden();
        $this->loginAs('bacsi.an@clinic.test');
        $this->get(route('employee-schedules.index'))->assertForbidden();
        $this->postJson(route('employee-schedules.store'), $this->payload())->assertForbidden();
        $this->loginAs('benhnhan@clinic.test');
        $this->get(route('admin.roster.index'))->assertForbidden();
        $this->get(route('employee-schedules.index'))->assertForbidden();
        $this->loginAs('admin@clinic.test');
        $this->get(route('admin.roster.index', ['date' => '2026-11-16']))->assertOk()->assertSee('Xếp lịch tự động')->assertSee('Lễ tân Demo');
    }

    public function test_registration_rejects_other_branches_past_times_and_rolls_back_a_partly_duplicated_batch(): void
    {
        $this->loginAs('letan@clinic.test');
        $branch2 = Branch::where('code', 'CN02')->value('id');
        $this->postJson(route('employee-schedules.store'), $this->payload(['branch_id' => $branch2]))->assertUnprocessable()->assertJsonValidationErrors('roster');
        $this->postJson(route('employee-schedules.store'), $this->payload(['from_date' => '2026-11-11']))->assertUnprocessable();
        $this->postJson(route('employee-schedules.store'), $this->payload(['to_date' => '2027-04-01']))->assertUnprocessable();
        $this->postJson(route('employee-schedules.store'), $this->payload(['weekdays' => []]))->assertUnprocessable();
        $this->submit(changes: ['from_date' => '2026-11-17', 'to_date' => '2026-11-17', 'session' => 'morning']);
        $this->postJson(route('employee-schedules.store'), $this->payload(['to_date' => '2026-11-17']))->assertUnprocessable();
        $this->assertDatabaseCount('staff_schedule_requests', 1);
        $registration = StaffScheduleRequest::firstOrFail();
        $this->patch(route('employee-schedules.withdraw', $registration))->assertRedirect();
        $this->assertNull($registration->fresh()->active_key);
        $this->submit(changes: ['to_date' => '2026-11-17']);
        $this->assertSame(4, StaffScheduleRequest::where('status', 'WAITING')->count());
        Carbon::setTestNow('2026-11-12 10:00:00');
        $this->postJson(route('employee-schedules.store'), $this->payload(['from_date' => '2026-11-12', 'to_date' => '2026-11-12']))->assertUnprocessable();
        $this->assertDatabaseCount('staff_schedule_requests', 5);
    }

    public function test_auto_arrangement_balances_hours_preserves_clinical_schedules_and_publishes_only_after_review(): void
    {
        foreach (['receptionist' => 'letan.second@clinic.test', 'cashier' => 'thungan.second@clinic.test'] as $role => $email) {
            $user = User::create(['role_id' => Role::where('code', $role)->value('id'), 'name' => 'Nhân viên thứ hai '.$role, 'email' => $email, 'password' => 'Password@123']);
            $user->branches()->attach($this->branch);
        }
        $clinical = DoctorSchedule::orderBy('id')->get()->toArray();
        foreach (['letan@clinic.test', 'letan.second@clinic.test', 'thungan@clinic.test', 'thungan.second@clinic.test'] as $email) {
            $this->submit($email, ['to_date' => '2026-11-21']);
        }
        $this->loginAs('admin@clinic.test');
        $this->post(route('admin.roster.auto'), ['date' => '2026-11-16', 'branch_id' => $this->branch, 'receptionist_count' => 1, 'cashier_count' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(24, StaffWorkShift::where('status', 'DRAFT')->count());
        $this->assertSame(24, StaffScheduleRequest::where('status', 'WAITING')->count());
        $loads = StaffWorkShift::get()->groupBy('user_id')->map(fn ($s) => $s->sum(fn ($row) => $row->minutes()));
        $this->assertCount(4, $loads);
        $this->assertSame(1260, $loads->min());
        $this->assertSame(1260, $loads->max());
        $this->post(route('admin.roster.auto'), ['date' => '2026-11-16', 'branch_id' => $this->branch, 'receptionist_count' => 1, 'cashier_count' => 1])->assertRedirect();
        $this->assertDatabaseCount('staff_work_shifts', 24);
        $focus = StaffWorkShift::latest('id')->firstOrFail()->request_id;
        $this->get(route('admin.roster.index', ['date' => '2026-11-16', 'branch_id' => $this->branch, 'focus' => $focus]))->assertOk()
            ->assertViewHas('registrations', fn ($r) => $r->total() === 1 && $r->first()->id === $focus)->assertViewHas('drafts', fn ($d) => $d->count() === 24);
        $version = $this->version();
        $this->loginAs('letan@clinic.test');
        $this->get(route('employee-schedules.index', ['date' => '2026-11-16']))->assertOk()->assertViewHas('shifts', fn ($s) => $s->isEmpty());
        $this->loginAs('admin@clinic.test');
        $this->post(route('admin.roster.publish'), ['date' => '2026-11-16', 'branch_id' => $this->branch, 'plan_version' => $version])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(24, StaffWorkShift::where('status', 'CONFIRMED')->count());
        $this->assertSame(24, StaffScheduleRequest::where('status', 'CONFIRMED')->count());
        $this->loginAs('letan@clinic.test');
        $this->get(route('employee-schedules.index', ['date' => '2026-11-16']))->assertOk()->assertViewHas('shifts', fn ($s) => $s->count() === 6 && $s->every(fn ($row) => $row->user_id === auth()->id()));
        $this->get(route('dashboard'))->assertOk()->assertViewHas('rosterNotice', fn ($n) => $n['confirmed'] === 6);
        $this->assertSame($clinical, DoctorSchedule::orderBy('id')->get()->toArray());
    }

    public function test_manual_arrangement_blocks_overlap_across_branches_and_daily_hours_overflow(): void
    {
        $user = $this->loginAs('letan@clinic.test');
        $branch2 = Branch::where('code', 'CN02')->value('id');
        $user->branches()->attach($branch2);
        $this->submit(changes: ['to_date' => '2026-11-17']);
        $registrations = StaffScheduleRequest::orderBy('id')->get();
        $first = $this->manual($registrations[0], ['branch_id' => $branch2, 'work_date' => '2026-11-17', 'start_time' => '09:00', 'end_time' => '12:00']);
        $this->assertSame($branch2, $first->branch_id);
        $this->postJson(route('admin.roster.arrange', $registrations[1]), ['branch_id' => $this->branch, 'work_date' => '2026-11-17', 'start_time' => '11:00', 'end_time' => '13:00', 'plan_version' => 'new'])
            ->assertUnprocessable()->assertJsonValidationErrors('roster');
        $this->postJson(route('admin.roster.arrange', $registrations[1]), ['branch_id' => $this->branch, 'work_date' => '2026-11-17', 'start_time' => '13:00', 'end_time' => '19:00', 'plan_version' => 'new'])
            ->assertUnprocessable()->assertJsonValidationErrors('roster');
        $this->assertDatabaseCount('staff_work_shifts', 1);
        $this->loginAs('letan@clinic.test');
        $this->postJson(route('employee-schedules.store'), $this->payload(['from_date' => '2026-11-17', 'to_date' => '2026-11-17', 'session' => 'morning']))->assertUnprocessable();
    }

    public function test_stale_manual_and_publish_versions_are_rejected_and_confirmed_shifts_need_reopening(): void
    {
        $this->submit(changes: ['session' => 'morning']);
        $registration = StaffScheduleRequest::firstOrFail();
        $shift = $this->manual($registration);
        $oldVersion = $this->version();
        $this->postJson(route('admin.roster.arrange', $registration), ['branch_id' => $this->branch, 'work_date' => '2026-11-16', 'start_time' => '09:00', 'end_time' => '11:30', 'plan_version' => 'new'])->assertUnprocessable();
        $this->manual($registration->fresh(), ['start_time' => '09:00']);
        $this->postJson(route('admin.roster.publish'), ['date' => '2026-11-16', 'plan_version' => $oldVersion])->assertUnprocessable()->assertJsonValidationErrors('roster');
        $this->assertSame(0, StaffWorkShift::where('status', 'CONFIRMED')->count());
        $this->post(route('admin.roster.publish'), ['date' => '2026-11-16', 'plan_version' => $this->version()])->assertRedirect()->assertSessionHasNoErrors();
        $this->postJson(route('admin.roster.publish'), ['date' => '2026-11-16', 'plan_version' => $this->version()])->assertUnprocessable();
        $this->patchJson(route('admin.roster.reject', $registration), ['reason' => 'Đã chốt'])->assertUnprocessable();
        $this->loginAs('letan@clinic.test');
        $this->patchJson(route('employee-schedules.withdraw', $registration))->assertUnprocessable();
        $this->loginAs('admin@clinic.test');
        $this->patch(route('admin.roster.reopen', $shift), ['reason' => 'Điều chỉnh phân công', 'plan_version' => $shift->fresh()->version()])->assertRedirect();
        $this->assertSame('DRAFT', $shift->fresh()->status);
        $this->patch(route('admin.roster.reject', $registration), ['reason' => 'Sắp xếp lại'])->assertRedirect();
        $this->assertSame('CANCELLED', $shift->fresh()->status);
        $this->assertSame('REJECTED', $registration->fresh()->status);
        $this->assertNull($registration->fresh()->active_key);
    }

    public function test_publish_rechecks_staff_eligibility_and_rolls_back_every_shift_on_failure(): void
    {
        $this->submit(changes: ['session' => 'morning']);
        $this->submit('thungan@clinic.test', ['session' => 'afternoon']);
        foreach (StaffScheduleRequest::orderBy('id')->get() as $registration) {
            $this->manual($registration);
        }
        $version = $this->version();
        User::where('email', 'thungan@clinic.test')->update(['is_active' => false, 'status' => 'LOCKED']);
        $this->postJson(route('admin.roster.publish'), ['date' => '2026-11-16', 'plan_version' => $version])->assertUnprocessable();
        $this->assertSame(0, StaffWorkShift::where('status', 'CONFIRMED')->count());
        $this->assertSame(2, StaffScheduleRequest::where('status', 'ARRANGED')->count());
        $this->assertDatabaseMissing('audit_logs', ['action' => 'staff_roster.published']);
    }

    public function test_withdrawal_cancels_the_unpublished_plan_and_guards_keep_confirmed_branch_assignments(): void
    {
        $this->submit();
        $registrations = StaffScheduleRequest::orderBy('id')->get();
        $draft = $this->manual($registrations[0]);
        $this->loginAs('letan@clinic.test');
        $this->patch(route('employee-schedules.withdraw', $registrations[0]))->assertRedirect();
        $this->assertSame('CANCELLED', $draft->fresh()->status);
        $shift = $this->manual($registrations[1]);
        $this->post(route('admin.roster.publish'), ['date' => '2026-11-16', 'plan_version' => $this->version()])->assertRedirect();
        $staff = User::where('email', 'letan@clinic.test')->firstOrFail();
        $branch2 = Branch::where('code', 'CN02')->value('id');
        $staff->branches()->attach($branch2);
        $this->putJson(route('admin.staff.update', $staff), ['status' => 'ACTIVE', 'branch_ids' => [$branch2]])->assertUnprocessable()->assertJsonValidationErrors('branch_ids');
        $branch = Branch::findOrFail($this->branch);
        $this->putJson(route('admin.branches.update', $branch), ['code' => $branch->code, 'name' => $branch->name, 'is_active' => false])->assertUnprocessable()->assertJsonValidationErrors('is_active');
        $this->assertTrue($branch->fresh()->is_active);
        Carbon::setTestNow('2026-11-16 13:30:00');
        $this->patchJson(route('admin.roster.reopen', $shift), ['reason' => 'Quá giờ', 'plan_version' => $shift->fresh()->version()])->assertUnprocessable();
    }
}
