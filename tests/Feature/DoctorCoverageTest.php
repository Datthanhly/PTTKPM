<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\PayrollEntry;
use App\Models\SalaryProfile;
use App\Models\ShiftTransferRequest;
use App\Models\Specialty;
use App\Models\User;
use App\Services\AppointmentBookingService;
use App\Services\DoctorCoverageService;
use App\Services\PayrollService;
use Carbon\Carbon;
use Database\Seeders\ClinicWorkforceSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DoctorCoverageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-13 07:00:00');
        $this->seed(DatabaseSeeder::class);
        Branch::create(['code' => '003', 'name' => 'Phòng khám An Tâm 3']);
        app(ClinicWorkforceSeeder::class)->run();
        $admin = User::where('email', 'admin@clinic.test')->firstOrFail();
        $this->actingAs($admin)->withSession(['password_hash_web' => $admin->password]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function arrange(int $days = 14, bool $sunday = true): array
    {
        return app(DoctorCoverageService::class)->arrange('2026-11-14', $days, $sunday);
    }

    private function assertCoverage(string $from, int $days, bool $sunday = true): void
    {
        $doctors = Doctor::with('user.branches')->get();
        $shifts = DoctorSchedule::where('is_open', true)->whereDate('work_date', '>=', $from)->whereDate('work_date', '<=', Carbon::parse($from)->addDays($days - 1))->get();
        $byBranchDay = $shifts->groupBy(fn ($s) => $s->branch_id.'|'.$s->work_date->format('Y-m-d'));
        foreach (Branch::where('is_active', true)->get() as $branch) {
            foreach (Specialty::where('is_active', true)->get() as $specialty) {
                $pool = $doctors->filter(fn ($d) => $d->specialty_id === $specialty->id && $d->is_active && $d->user->is_active && $d->user->status === 'ACTIVE' && $d->user->branches->contains('id', $branch->id));
                $this->assertGreaterThanOrEqual(2, $pool->count());
                for ($i = 0; $i < $days; $i++) {
                    $day = Carbon::parse($from)->addDays($i);
                    if (! $sunday && $day->isSunday()) {
                        continue;
                    }
                    foreach (config('clinic.working_hours') as [$start, $end]) {
                        $this->assertTrue($byBranchDay->get($branch->id.'|'.$day->format('Y-m-d'), collect())->contains(fn ($s) => $s->branch_id === $branch->id && $pool->contains('id', $s->doctor_id)
                            && $s->work_date->isSameDay($day) && substr($s->start_time, 0, 5) <= $start && substr($s->end_time, 0, 5) >= $end), "$branch->code / $specialty->code / {$day->format('Y-m-d')} / $start");
                    }
                }
            }
        }
        foreach ($shifts->groupBy(fn ($s) => $s->doctor_id.'|'.$s->work_date->format('Y-m-d')) as $doctorDay) {
            $lastEnd = null;
            foreach ($doctorDay->sortBy('start_time') as $shift) {
                if ($lastEnd) {
                    $this->assertGreaterThanOrEqual($lastEnd, $shift->start_time, 'A doctor must not work simultaneously across branches.');
                }
                $lastEnd = $shift->end_time;
            }
        }
    }

    public function test_two_doctors_per_specialty_per_branch_cover_every_period_and_existing_patient_data_and_payroll_are_unchanged(): void
    {
        $doctor = Doctor::firstOrFail();
        $doctor->user->update(['password' => 'PrivateExisting@123']);
        app(PayrollService::class)->saveProfile($doctor->user, ['version' => 0, 'pay_basis' => 'HOUR', 'unit_rate' => 777000]);
        $entry = app(PayrollService::class)->generate($doctor->user, ['month' => '2026-11', 'profile_version' => 1]);
        app(PayrollService::class)->payment($entry, ['version' => 1, 'status' => 'PAID', 'reason' => 'Đã chi trả trước khi phân ca']);
        $oldUsers = DB::table('users')->orderBy('id')->get()->toArray();
        $oldAssignments = DB::table('branch_user')->orderBy('user_id')->orderBy('branch_id')->get()->toArray();
        $appointments = Appointment::orderBy('id')->get()->toArray();
        $payroll = PayrollEntry::orderBy('id')->get()->toArray();
        $oldProfile = $doctor->user->salaryProfile()->firstOrFail()->toArray();
        $booked = Appointment::firstOrFail()->schedule->toArray();
        $past = DoctorSchedule::create(['doctor_id' => $doctor->id, 'branch_id' => $doctor->user->branches()->first()->id, 'work_date' => '2026-11-12', 'start_time' => '08:00', 'end_time' => '11:30', 'slot_minutes' => 30]);
        $pastBefore = $past->refresh()->toArray();
        $result = $this->arrange();
        $this->assertCount(33, $result['created_doctors']);
        $this->assertSame(66, Doctor::count());
        $this->assertSame(924, $result['checked_cells']);
        $this->assertGreaterThan(0, $result['reassigned_shifts']);
        $this->assertCoverage('2026-11-14', 14);
        $this->assertEquals($oldUsers, DB::table('users')->whereIn('id', array_column($oldUsers, 'id'))->orderBy('id')->get()->toArray());
        $this->assertEquals($oldAssignments, DB::table('branch_user')->whereIn('user_id', array_column($oldUsers, 'id'))->orderBy('user_id')->orderBy('branch_id')->get()->toArray());
        $this->assertSame($appointments, Appointment::orderBy('id')->get()->toArray());
        $this->assertSame($payroll, PayrollEntry::orderBy('id')->get()->toArray());
        $this->assertSame($oldProfile, $doctor->user->salaryProfile()->firstOrFail()->toArray());
        $this->assertSame($booked, DoctorSchedule::findOrFail($booked['id'])->toArray());
        $this->assertSame($pastBefore, $past->fresh()->toArray());
        foreach (Doctor::with('user.salaryProfile')->whereIn('id', $result['created_doctors'])->get() as $newDoctor) {
            $this->assertSame(500000, $newDoctor->user->salaryProfile->unit_rate);
            $this->assertSame('HOUR', $newDoctor->user->salaryProfile->pay_basis);
            $this->assertFalse($newDoctor->user->hasPermission('LAB_RESULT_VERIFY'));
        }
        $shiftsBefore = DoctorSchedule::orderBy('id')->get()->toArray();
        $repeat = $this->arrange();
        $this->assertSame([], $repeat['created_doctors']);
        $this->assertSame(0, $repeat['created_shifts']);
        $this->assertSame(0, $repeat['reassigned_shifts']);
        $this->assertSame(0, $repeat['closed_empty_shifts']);
        $this->assertSame($shiftsBefore, DoctorSchedule::orderBy('id')->get()->toArray());
        $newDoctor = Doctor::findOrFail($result['created_doctors'][0]);
        $this->assertNotEmpty(app(AppointmentBookingService::class)->availability($newDoctor, $newDoctor->user->branches()->first()->id, '2026-11-15'));
    }

    public function test_preview_rolls_back_new_accounts_salaries_and_shifts_and_invalid_dates_do_not_write(): void
    {
        $before = [User::count(), Doctor::count(), DoctorSchedule::count(), SalaryProfile::count(), DB::table('audit_logs')->count()];
        $this->artisan('clinic:coverage', ['--from' => '2026-11-14', '--days' => 3, '--include-sunday' => true])->assertSuccessful();
        $this->assertSame($before, [User::count(), Doctor::count(), DoctorSchedule::count(), SalaryProfile::count(), DB::table('audit_logs')->count()]);
        $this->artisan('clinic:coverage', ['--from' => '2026-02-30', '--apply' => true])->assertFailed();
        $this->artisan('clinic:coverage', ['--from' => '2026-11-13', '--apply' => true])->assertFailed();
        $this->artisan('clinic:coverage', ['--days' => 91, '--apply' => true])->assertFailed();
        $this->assertSame($before, [User::count(), Doctor::count(), DoctorSchedule::count(), SalaryProfile::count(), DB::table('audit_logs')->count()]);
    }

    public function test_leave_closed_custom_and_transfer_linked_shifts_are_respected(): void
    {
        $this->arrange(3, false);
        $source = DoctorSchedule::whereDate('work_date', '2026-11-16')->where('start_time', '08:00')->whereDoesntHave('appointments')->firstOrFail();
        $receiver = Doctor::where('specialty_id', $source->doctor->specialty_id)->whereKeyNot($source->doctor_id)->whereHas('user.branches', fn ($q) => $q->where('branches.id', $source->branch_id))->firstOrFail();
        $transfer = ShiftTransferRequest::create(['doctor_schedule_id' => $source->id, 'sender_doctor_id' => $source->doctor_id, 'receiver_doctor_id' => $receiver->id, 'branch_id' => $source->branch_id,
            'work_date' => $source->work_date, 'start_time' => $source->start_time, 'end_time' => $source->end_time, 'note' => 'Đang chờ đồng ý', 'pending_key' => $source->id]);
        $closed = DoctorSchedule::whereDate('work_date', '2026-11-14')->where('end_time', '17:00')->whereDoesntHave('appointments')->firstOrFail();
        $closed->update(['is_open' => false]);
        $custom = DoctorSchedule::create(['doctor_id' => $source->doctor_id, 'branch_id' => $source->branch_id, 'work_date' => '2026-11-16', 'start_time' => '06:00', 'end_time' => '07:00', 'slot_minutes' => 30]);
        $original = [$source->attributesToArray(), $transfer->refresh()->toArray(), $closed->fresh()->toArray(), $custom->refresh()->toArray()];
        DB::table('doctor_time_off')->insert(['doctor_id' => $closed->doctor_id, 'starts_at' => '2026-11-16 13:30:00', 'ends_at' => '2026-11-16 17:00:00', 'reason' => 'Nghỉ chiều', 'created_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
        $this->arrange(3, false);
        $this->assertSame($original, [$source->fresh()->attributesToArray(), $transfer->fresh()->toArray(), $closed->fresh()->toArray(), $custom->fresh()->toArray()]);
        $this->assertCoverage('2026-11-14', 3, false);
        $this->assertFalse(DoctorSchedule::where('doctor_id', $closed->doctor_id)->whereDate('work_date', '2026-11-16')->where('is_open', true)->where('start_time', '13:30')->exists());
    }

    public function test_impossible_coverage_rolls_back_the_whole_batch_and_does_not_reactivate_locked_doctors(): void
    {
        $this->arrange(3);
        $pair = Doctor::where('specialty_id', Doctor::first()->specialty_id)->whereHas('user.branches', fn ($q) => $q->where('branches.id', Branch::where('code', 'CN01')->value('id')))->get();
        foreach ($pair as $doctor) {
            DB::table('doctor_time_off')->insert(['doctor_id' => $doctor->id, 'starts_at' => '2026-11-16 13:30:00', 'ends_at' => '2026-11-16 17:00:00', 'reason' => 'Nghỉ đồng thời', 'created_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
        }
        $before = DoctorSchedule::orderBy('id')->get()->toArray();
        $audits = DB::table('audit_logs')->count();
        try {
            $this->arrange(3);
            $this->fail('Should reject an uncovered cell.');
        } catch (ValidationException $error) {
            $this->assertStringContainsString('Không có bác sĩ trống', implode(' ', $error->validator->errors()->all()));
        }
        $this->assertSame($before, DoctorSchedule::orderBy('id')->get()->toArray());
        $this->assertSame($audits, DB::table('audit_logs')->count());
        $locked = $pair->first()->user;
        $locked->update(['is_active' => false, 'status' => 'LOCKED']);
        DB::table('doctor_time_off')->whereIn('doctor_id', $pair->pluck('id'))->delete();
        $result = $this->arrange(3);
        $this->assertCount(1, $result['created_doctors']);
        $this->assertFalse($locked->fresh()->is_active);
        $this->assertSame('LOCKED', $locked->fresh()->status);
        $this->assertCoverage('2026-11-14', 3);
    }
}
