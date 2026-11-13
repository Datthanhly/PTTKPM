<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\DoctorSchedule;
use App\Models\PayrollEntry;
use App\Models\SalaryProfile;
use App\Models\StaffScheduleRequest;
use App\Models\StaffWorkShift;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PayrollTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-13 12:00:00');
        $this->seed(DatabaseSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function loginAs(string $email = 'admin@clinic.test'): User
    {
        $user = User::where('email', $email)->firstOrFail();
        $this->actingAs($user)->withSession(['password_hash_web' => $user->password]);

        return $user;
    }

    private function employee(string $email = 'bacsi.an@clinic.test'): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function profile(User $user, string $basis = 'HOUR', int $rate = 100001): SalaryProfile
    {
        $this->loginAs();
        $this->put(route('admin.payroll.profile', $user), ['version' => $user->salaryProfile?->version ?? 0, 'pay_basis' => $basis, 'unit_rate' => $rate])->assertRedirect()->assertSessionHasNoErrors();

        return SalaryProfile::where('user_id', $user->id)->firstOrFail();
    }

    private function generate(User $user, string $month = '2026-11'): PayrollEntry
    {
        $this->loginAs();
        $this->post(route('admin.payroll.generate', $user), ['month' => $month, 'profile_version' => $user->salaryProfile()->value('version')])->assertRedirect()->assertSessionHasNoErrors();

        return PayrollEntry::where('user_id', $user->id)->whereDate('pay_month', $month.'-01')->firstOrFail();
    }

    private function doctorShift(User $user, array $changes = []): DoctorSchedule
    {
        return DoctorSchedule::create(array_replace(['doctor_id' => $user->doctor->id, 'branch_id' => $user->branches()->first()->id,
            'work_date' => '2026-11-12', 'start_time' => '08:00', 'end_time' => '11:30', 'slot_minutes' => 30, 'capacity' => 1, 'is_open' => true], $changes));
    }

    private function staffShift(User $user, string $date, string $status = 'CONFIRMED', string $start = '08:00', string $end = '11:30'): StaffWorkShift
    {
        $branch = $user->branches()->first()->id;
        $registration = StaffScheduleRequest::create(['user_id' => $user->id, 'branch_id' => $branch, 'role_code' => $user->role->code,
            'work_date' => $date, 'start_time' => $start, 'end_time' => $end, 'status' => $status === 'CONFIRMED' ? 'CONFIRMED' : 'ARRANGED']);

        return StaffWorkShift::create(['request_id' => $registration->id, 'user_id' => $user->id, 'branch_id' => $branch, 'role_code' => $user->role->code,
            'work_date' => $date, 'start_time' => $start, 'end_time' => $end, 'status' => $status,
            'created_by' => $this->employee('admin@clinic.test')->id]);
    }

    private function changes(PayrollEntry $entry, array $changes = []): array
    {
        return array_replace(['version' => $entry->version, 'unit_rate' => $entry->unit_rate,
            'hours' => intdiv($entry->worked_minutes, 60), 'minutes' => $entry->worked_minutes % 60, 'worked_shifts' => $entry->worked_shifts,
            'allowance' => 100000, 'bonus' => 50000, 'deduction' => 20000, 'reason' => 'Đối chiếu số công thực tế', 'note' => 'Ghi chú riêng của nhân viên'], $changes);
    }

    public function test_doctor_hourly_pay_uses_only_ended_open_shifts_excludes_leave_and_rounds_to_vnd(): void
    {
        $user = $this->employee();
        $this->profile($user);
        $this->doctorShift($user);
        $this->doctorShift($user, ['work_date' => '2026-11-13', 'start_time' => '07:00', 'end_time' => '08:01']);
        $this->doctorShift($user, ['work_date' => '2026-11-11', 'is_open' => false]);
        $this->doctorShift($user, ['work_date' => '2026-11-13', 'start_time' => '11:00', 'end_time' => '13:00']);
        $this->doctorShift($user, ['work_date' => '2026-10-12']);
        $this->doctorShift($user, ['work_date' => '2026-11-10']);
        DB::table('doctor_time_off')->insert(['doctor_id' => $user->doctor->id, 'starts_at' => '2026-11-10 09:00:00', 'ends_at' => '2026-11-10 10:00:00', 'reason' => 'Nghỉ', 'created_by' => $this->employee('admin@clinic.test')->id, 'created_at' => now(), 'updated_at' => now()]);
        $entry = $this->generate($user);
        $this->assertSame(271, $entry->worked_minutes);
        $this->assertSame(2, $entry->worked_shifts);
        $this->assertSame(451671, $entry->base_amount);
        $this->assertSame(451671, $entry->net_amount);
        $this->assertSame('UNPAID', $entry->status);
        $this->assertSame(1, $entry->source_shifts['excluded_leave_shifts']);
        $this->assertCount(2, $entry->source_shifts['shifts']);
        $this->get(route('admin.payroll.show', $entry))->assertOk()->assertSee('451.671')->assertSee('Các ca dùng để tính ban đầu');
    }

    public function test_receptionist_and_cashier_have_individual_rates_and_only_confirmed_ended_staff_shifts_count(): void
    {
        foreach (['letan@clinic.test' => 300000, 'thungan@clinic.test' => 400000] as $email => $rate) {
            $user = $this->employee($email);
            $this->profile($user, 'SHIFT', $rate);
            $this->staffShift($user, '2026-11-12');
            $this->staffShift($user, '2026-11-13');
            $this->staffShift($user, '2026-11-11', 'DRAFT');
            $this->staffShift($user, '2026-11-10', 'CANCELLED');
            $this->staffShift($user, '2026-11-14');
            $entry = $this->generate($user);
            $this->assertSame(2, $entry->worked_shifts);
            $this->assertSame(420, $entry->worked_minutes);
            $this->assertSame($rate * 2, $entry->net_amount);
            $this->loginAs($email);
            $this->get(route('payroll.mine'))->assertOk()->assertSee(number_format($rate * 2, 0, ',', '.'));
        }
        $this->loginAs();
        $this->get(route('admin.payroll.index'))->assertOk()->assertSee('Lễ tân Demo')->assertSee('Thu ngân Demo')->assertSee('Bảng lương');
    }

    public function test_salary_privacy_cannot_be_bypassed_by_ids_query_parameters_or_any_employee_write(): void
    {
        $user = $this->employee();
        $this->profile($user, 'SHIFT', 765432);
        $this->doctorShift($user);
        $entry = $this->generate($user);
        foreach (['bacsi.ha@clinic.test', 'letan@clinic.test', 'thungan@clinic.test', 'letan.cn2@clinic.test'] as $email) {
            $this->loginAs($email);
            $this->get(route('payroll.mine', ['user_id' => $user->id, 'employee' => $user->id]))->assertOk()->assertDontSee('765.432')->assertViewHas('entries', fn ($e) => $e->total() === 0);
            $this->get(route('payroll.mine.show', $entry))->assertForbidden();
            $this->get(route('admin.payroll.index'))->assertForbidden();
            $this->get(route('admin.payroll.show', $entry))->assertForbidden();
            $this->putJson(route('admin.payroll.profile', $user), [])->assertForbidden();
            $this->postJson(route('admin.payroll.generate', $user), [])->assertForbidden();
            $this->putJson(route('admin.payroll.update', $entry), [])->assertForbidden();
            $this->postJson(route('admin.payroll.recalculate', $entry), [])->assertForbidden();
            $this->patchJson(route('admin.payroll.payment', $entry), [])->assertForbidden();
        }
        $this->loginAs($user->email);
        $this->get(route('payroll.mine.show', $entry))->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertDontSee('name="unit_rate"', false)->assertSee('Lương của tôi');
        $this->loginAs('benhnhan@clinic.test');
        $this->get(route('payroll.mine'))->assertForbidden();
        $this->get(route('payroll.mine.show', $entry))->assertForbidden();
        $this->get(route('home'))->assertOk()->assertDontSee('staff-shell.css')->assertDontSee('Lương của tôi');
        $this->loginAs();
        $this->putJson(route('admin.payroll.profile', $this->employee('benhnhan@clinic.test')), ['version' => 0, 'pay_basis' => 'HOUR', 'unit_rate' => 1])->assertForbidden();
    }

    public function test_profile_changes_do_not_reprice_old_months_and_duplicate_generation_never_overwrites(): void
    {
        $user = $this->employee();
        $profile = $this->profile($user, 'SHIFT', 123456);
        $this->doctorShift($user);
        $entry = $this->generate($user);
        $this->loginAs();
        $this->put(route('admin.payroll.profile', $user), ['version' => $profile->version, 'pay_basis' => 'HOUR', 'unit_rate' => 999999])->assertSessionHasNoErrors();
        $this->assertSame(123456, $entry->fresh()->net_amount);
        $this->assertSame('SHIFT', $entry->fresh()->pay_basis);
        $this->putJson(route('admin.payroll.profile', $user), ['version' => $profile->version, 'pay_basis' => 'SHIFT', 'unit_rate' => 1])->assertUnprocessable()->assertJsonValidationErrors('payroll');
        $this->postJson(route('admin.payroll.generate', $user), ['month' => '2026-11', 'profile_version' => $profile->version + 1])->assertUnprocessable();
        $this->postJson(route('admin.payroll.generate', $user), ['month' => '2026-10', 'profile_version' => $profile->version])->assertUnprocessable();
        $this->assertDatabaseCount('payroll_entries', 1);
        $this->assertSame(123456, $entry->fresh()->net_amount);
    }

    public function test_admin_adjustment_recalculation_and_paid_lock_preserve_auditable_snapshots(): void
    {
        $user = $this->employee();
        $this->profile($user, 'HOUR', 100000);
        $this->doctorShift($user);
        $entry = $this->generate($user);
        $this->put(route('admin.payroll.update', $entry), $this->changes($entry, ['hours' => 4, 'minutes' => 15, 'status' => 'PAID', 'user_id' => 1]))->assertRedirect()->assertSessionHasNoErrors();
        $entry->refresh();
        $this->assertSame(555000, $entry->net_amount);
        $this->assertSame('UNPAID', $entry->status);
        $this->assertSame($user->id, $entry->user_id);
        $this->assertSame(210, $entry->source_shifts['shifts'][0]['minutes']);
        $this->putJson(route('admin.payroll.update', $entry), $this->changes($entry, ['version' => 1]))->assertUnprocessable();
        $this->doctorShift($user, ['work_date' => '2026-11-11']);
        $this->post(route('admin.payroll.recalculate', $entry), ['version' => $entry->version, 'reason' => 'Bổ sung ca làm đã kết thúc'])->assertSessionHasNoErrors();
        $entry->refresh();
        $this->assertSame(420, $entry->worked_minutes);
        $this->assertSame(830000, $entry->net_amount);
        $this->patch(route('admin.payroll.payment', $entry), ['version' => $entry->version, 'status' => 'PAID', 'reason' => 'Đã chuyển lương', 'reference' => 'CT-202611-001'])->assertSessionHasNoErrors();
        $entry->refresh();
        $this->assertSame('PAID', $entry->status);
        $this->assertNotNull($entry->paid_at);
        $this->assertSame(auth()->id(), $entry->paid_by);
        $this->assertSame('CT-202611-001', $entry->payment_reference);
        $this->putJson(route('admin.payroll.update', $entry), $this->changes($entry))->assertUnprocessable();
        $this->postJson(route('admin.payroll.recalculate', $entry), ['version' => $entry->version, 'reason' => 'Không sửa ca đã trả'])->assertUnprocessable();
        $this->patchJson(route('admin.payroll.payment', $entry), ['version' => $entry->version, 'status' => 'PAID', 'reason' => 'Gửi lại'])->assertUnprocessable();
        $this->get(route('admin.payroll.show', $entry))->assertOk()->assertDontSee('name="unit_rate"', false)->assertSee('Đã thanh toán');
        $this->patchJson(route('admin.payroll.payment', $entry), ['version' => $entry->version, 'status' => 'UNPAID'])->assertUnprocessable();
        $this->patch(route('admin.payroll.payment', $entry), ['version' => $entry->version, 'status' => 'UNPAID', 'reason' => 'Ghi nhận sai chứng từ, cần đối chiếu'])->assertSessionHasNoErrors();
        $this->assertNull($entry->fresh()->paid_at);
        $this->assertSame(830000, $entry->fresh()->net_amount);
        $this->assertSame(1, AuditLog::where('action', 'payroll.payment_reversed')->count());
        $this->assertSame('CT-202611-001', AuditLog::where('action', 'payroll.payment_reversed')->first()->old_values['payment_reference']);
    }

    public function test_invalid_money_time_future_month_and_source_conflicts_do_not_save_partial_payroll(): void
    {
        $user = $this->employee();
        $this->profile($user);
        foreach ([-1, 0, '1.50', 100000001] as $rate) {
            $this->putJson(route('admin.payroll.profile', $user), ['version' => 1, 'pay_basis' => 'HOUR', 'unit_rate' => $rate])->assertUnprocessable();
        }
        $this->postJson(route('admin.payroll.generate', $user), ['month' => '2026-12', 'profile_version' => 1])->assertUnprocessable();
        $this->postJson(route('admin.payroll.generate', $this->employee('letan@clinic.test')), ['month' => '2026-11', 'profile_version' => 1])->assertUnprocessable();
        $first = $this->doctorShift($user);
        $overlap = $this->doctorShift($user, ['branch_id' => Branch::where('code', 'CN02')->value('id'), 'start_time' => '09:00', 'end_time' => '12:00']);
        $this->postJson(route('admin.payroll.generate', $user), ['month' => '2026-11', 'profile_version' => 1])->assertUnprocessable()->assertJsonValidationErrors('payroll');
        $this->assertDatabaseCount('payroll_entries', 0);
        $overlap->update(['is_open' => false]);
        $entry = $this->generate($user);
        $this->putJson(route('admin.payroll.update', $entry), $this->changes($entry, ['deduction' => 999999999]))->assertUnprocessable();
        $this->putJson(route('admin.payroll.update', $entry), $this->changes($entry, ['hours' => 721]))->assertUnprocessable();
        $this->putJson(route('admin.payroll.update', $entry), $this->changes($entry, ['minutes' => 60]))->assertUnprocessable();
        $this->assertSame(350004, $entry->fresh()->net_amount);
        $this->assertSame(1, $entry->fresh()->version);
        $this->assertSame($first->id, $entry->source_shifts['shifts'][0]['id']);
    }

    public function test_payment_stale_versions_are_blocked_and_sidebar_keeps_each_role_routes(): void
    {
        $user = $this->employee();
        $this->profile($user);
        $entry = $this->generate($user);
        $this->put(route('admin.payroll.update', $entry), $this->changes($entry))->assertSessionHasNoErrors();
        $this->patchJson(route('admin.payroll.payment', $entry), ['version' => 1, 'status' => 'PAID', 'reason' => 'Tab cũ'])->assertUnprocessable();
        $this->assertSame('UNPAID', $entry->fresh()->status);
        foreach (['admin@clinic.test', 'bacsi.an@clinic.test', 'letan@clinic.test', 'thungan@clinic.test'] as $email) {
            $this->loginAs($email);
            $this->get(route('home'))->assertOk()->assertSee('staff-shell.css')->assertSee('aria-controls="main-navigation"', false);
        }
        $this->loginAs('bacsi.an@clinic.test');
        $this->get(route('payroll.mine', ['month' => '2026-11']))->assertOk()->assertViewHas('entries', fn ($e) => $e->total() === 1)->assertDontSee('Quản lý bảng lương');
        $this->get(route('payroll.mine', ['month' => '2026-10']))->assertOk()->assertViewHas('entries', fn ($e) => $e->total() === 0);
    }
}
