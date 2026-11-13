<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Services\AppointmentBookingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClinicSchedules extends Command
{
    protected $signature = 'clinic:schedules {--days=14 : Số ngày kể từ hôm nay, tối đa 90}
        {--doctor= : Mã bác sĩ, ví dụ BS0001; bỏ trống để tạo cho các bác sĩ đang hoạt động}
        {--branch= : Mã chi nhánh; mặc định dùng chi nhánh hoạt động đầu tiên được phân công}';

    protected $description = 'Bổ sung ca sáng và chiều, từ thứ Hai đến thứ Bảy; giữ nguyên ca đã có';

    public function handle(AppointmentBookingService $booking): int
    {
        $days = filter_var($this->option('days'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 90]]);
        if ($days === false) {
            $this->error('--days phải là số nguyên từ 1 đến 90.');

            return self::FAILURE;
        }
        $doctors = Doctor::where('is_active', true)
            ->whereHas('user', fn ($q) => $q->where('is_active', true)->where('status', 'ACTIVE'))
            ->when($this->option('doctor'), fn ($q, $code) => $q->where('doctor_code', $code))
            ->with(['user.branches' => fn ($q) => $q->where('is_active', true)->orderBy('branches.id')])->get();
        $rows = [];
        foreach ($doctors as $doctor) {
            $branch = $this->option('branch')
                ? $doctor->user->branches->firstWhere('code', $this->option('branch'))
                : $doctor->user->branches->first();
            if (! $branch) {
                $this->warn($doctor->doctor_code.': không có phân công tại chi nhánh phù hợp, đã bỏ qua.');

                continue;
            }
            $created = DB::transaction(function () use ($doctor, $branch, $days, $booking) {
                $booking->lockDoctor($doctor->id);
                $created = 0;
                for ($i = 0; $i < $days; $i++) {
                    $date = today()->addDays($i);
                    if ($date->isSunday()) {
                        continue;
                    }
                    foreach (config('clinic.working_hours') as [$start, $end]) {
                        if ($date->copy()->setTimeFromTimeString($end)->lte(now())) {
                            continue;
                        }
                        // Closed or overlapping shifts are intentional configuration; never replace them.
                        $exists = DoctorSchedule::where('doctor_id', $doctor->id)->whereDate('work_date', $date)
                            ->where('start_time', '<', $end)->where('end_time', '>', $start)->exists();
                        if ($exists) {
                            continue;
                        }
                        $schedule = DoctorSchedule::create([
                            'doctor_id' => $doctor->id, 'branch_id' => $branch->id, 'work_date' => $date,
                            'start_time' => $start, 'end_time' => $end,
                            'slot_minutes' => config('clinic.slot_minutes'), 'capacity' => 1, 'is_open' => true,
                        ]);
                        AuditLog::record('schedule.created_from_console', $schedule, [], $schedule->only(['work_date', 'start_time', 'end_time', 'slot_minutes', 'capacity']));
                        $created++;
                    }
                }

                return $created;
            }, 5);
            $rows[] = [$doctor->doctor_code, $doctor->user->name, $branch->code, $created];
        }
        if ($rows === []) {
            $this->error('Không tìm thấy bác sĩ đang hoạt động và được phân công chi nhánh phù hợp.');

            return self::FAILURE;
        }
        $this->table(['Mã', 'Bác sĩ', 'Chi nhánh', 'Ca mới'], $rows);
        $this->info('Đã bổ sung lịch sáng/chiều cho '.$days.' ngày kể từ '.today()->format('d/m/Y').'. Giữ nguyên các ca và lịch hẹn đã có.');

        return self::SUCCESS;
    }
}
