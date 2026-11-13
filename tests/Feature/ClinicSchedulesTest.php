<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Services\AppointmentBookingService;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClinicSchedulesTest extends TestCase
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

    public function test_both_sessions_produce_bookable_slots_and_repeating_keeps_existing_shifts(): void
    {
        $doctor = Doctor::where('doctor_code', 'BS0001')->firstOrFail();
        $branch = Branch::where('code', 'CN01')->firstOrFail();
        $this->artisan('clinic:schedules', ['--days' => 1, '--doctor' => 'BS0001'])->assertSuccessful();
        $shifts = $doctor->schedules()->whereDate('work_date', today())->orderBy('start_time')->get();
        $this->assertCount(2, $shifts);
        $this->assertSame(['08:00', '13:30'], $shifts->map(fn ($s) => substr($s->start_time, 0, 5))->all());
        $slots = app(AppointmentBookingService::class)->availability($doctor, $branch->id, today()->format('Y-m-d'));
        $this->assertCount(14, $slots);
        $this->assertSame('08:00', $slots[0]['start_time']);
        $this->assertSame('11:30', $slots[6]['end_time']);
        $this->assertSame('13:30', $slots[7]['start_time']);
        $this->assertSame('17:00', $slots[13]['end_time']);

        $shifts->first()->update(['capacity' => 3]);
        $this->artisan('clinic:schedules', ['--days' => 1, '--doctor' => 'BS0001'])->assertSuccessful();
        $this->assertSame(2, $doctor->schedules()->whereDate('work_date', today())->count());
        $this->assertSame(3, (int) $shifts->first()->fresh()->capacity);
    }

    public function test_existing_closed_shift_at_another_branch_is_preserved_and_blocks_overlap(): void
    {
        $doctor = Doctor::where('doctor_code', 'BS0001')->firstOrFail();
        $branch2 = Branch::where('code', 'CN02')->firstOrFail();
        $doctor->user->branches()->syncWithoutDetaching([$branch2->id]);
        $closed = DoctorSchedule::create([
            'doctor_id' => $doctor->id, 'branch_id' => $branch2->id, 'work_date' => today(),
            'start_time' => '09:00', 'end_time' => '10:00', 'slot_minutes' => 20, 'capacity' => 2, 'is_open' => false,
        ]);
        $this->artisan('clinic:schedules', ['--days' => 1, '--doctor' => 'BS0001', '--branch' => 'CN01'])->assertSuccessful();
        $this->assertSame(2, $doctor->schedules()->whereDate('work_date', today())->count());
        $this->assertFalse($closed->fresh()->is_open);
        $this->assertSame(20, (int) $closed->fresh()->slot_minutes);
        $this->assertDatabaseHas('doctor_schedules', ['doctor_id' => $doctor->id, 'start_time' => '13:30', 'is_open' => true]);
        $this->artisan('clinic:schedules', ['--days' => 0])->assertFailed();
    }
}
