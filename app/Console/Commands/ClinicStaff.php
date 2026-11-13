<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Doctor;
use App\Models\Specialty;
use App\Models\User;
use Database\Seeders\ClinicWorkforceSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ClinicStaff extends Command
{
    protected $signature = 'clinic:staff {--report : Xuất danh sách tài khoản demo vào document/Clinic_Demo_Accounts.md}';

    protected $description = 'Bổ sung 3 bác sĩ mỗi khoa, chia đều cơ sở; mỗi cơ sở 2 lễ tân và 2 thu ngân, giữ dữ liệu cũ';

    public function handle(ClinicWorkforceSeeder $seeder): int
    {
        $seeder->run();
        $this->info('Đã thêm '.count($seeder->createdDoctorIds).' bác sĩ, '.count($seeder->createdStaffIds).' nhân viên và '.$seeder->createdSchedules.' ca làm.');
        $branches = Branch::where('is_active', true)->orderBy('id')->get();
        $rows = [];
        foreach ($branches as $branch) {
            $counts = [];
            foreach (['doctor', 'receptionist', 'cashier'] as $role) {
                $counts[] = $branch->users()->where('is_active', true)->where('status', 'ACTIVE')
                    ->whereHas('role', fn ($q) => $q->where('code', $role))
                    ->when($role === 'doctor', fn ($q) => $q->whereHas('doctor', fn ($d) => $d->where('is_active', true)))->count();
            }
            $rows[] = [$branch->code, $branch->name, ...$counts];
        }
        $this->table(['Mã', 'Cơ sở', 'Bác sĩ', 'Lễ tân', 'Thu ngân'], $rows);
        $this->line('Mật khẩu tài khoản mới: Password@123. Tài khoản, mật khẩu và lịch hẹn cũ được giữ nguyên.');
        if ($this->option('report')) {
            $this->report();
        }

        return self::SUCCESS;
    }

    private function report(): void
    {
        $lines = [
            '# Danh sách nhân sự demo phòng khám', '',
            'Dữ liệu giả lập phục vụ đồ án. Mỗi chuyên khoa có mục tiêu 3 bác sĩ, mỗi cơ sở có 2 lễ tân và 2 thu ngân. Chỉ bổ sung phần thiếu, không chuyển nhân sự hoặc sửa tài khoản cũ.', '',
            '**Tài khoản mới dùng mật khẩu `Password@123`. Mật khẩu tài khoản cũ giữ nguyên; danh sách này không xác nhận mật khẩu cũ.**', '',
            'Bác sĩ mới được tạo ca 08:00–11:30 và 13:30–17:00, 30 phút/lượt, trong 14 ngày kể từ ngày bổ sung; nghỉ Chủ nhật và bỏ qua ca đã bắt đầu. Tự đăng ký/thay đổi ca tại **Lịch của tôi**.', '',
            'Để bổ sung lại sau này: `php artisan clinic:staff --report`. Chạy lại không tạo trùng khi đã đủ nhân sự; không mở lại tài khoản bị khóa hoặc sửa phân công cũ.', '',
            '## Phân bổ bác sĩ', '', '| Chuyên khoa | Bác sĩ đang hoạt động |', '|---|---|',
        ];
        foreach (Specialty::where('is_active', true)->orderBy('id')->get() as $specialty) {
            $count = Doctor::where('specialty_id', $specialty->id)->where('is_active', true)
                ->whereHas('user', fn ($q) => $q->where('is_active', true)->where('status', 'ACTIVE')->whereHas('role', fn ($r) => $r->where('code', 'doctor')))->count();
            $lines[] = '| '.$this->cell($specialty->name).' | '.$count.' |';
        }
        $lines = [...$lines, '', '## Tài khoản', '', '| Họ tên | Vai trò | Chuyên khoa | Cơ sở | Email |', '|---|---|---|---|---|'];
        $users = User::with(['role', 'branches', 'doctor.specialty'])->where('email', 'like', '%@clinic.test')
            ->whereHas('role', fn ($q) => $q->whereIn('code', ['doctor', 'receptionist', 'cashier']))->orderBy('role_id')->orderBy('name')->get();
        foreach ($users as $user) {
            $cells = [$user->name, $user->role->name, $user->doctor?->specialty?->name ?? '—', $user->branches->pluck('name')->join(', '), $user->email];
            $lines[] = '| '.implode(' | ', array_map(fn ($value) => $this->cell($value), $cells)).' |';
        }
        File::ensureDirectoryExists(base_path('document'));
        File::put(base_path('document/Clinic_Demo_Accounts.md'), implode(PHP_EOL, $lines).PHP_EOL);
        $this->info('Danh sách: document/Clinic_Demo_Accounts.md');
    }

    private function cell(string $value): string
    {
        return str_replace(["\r", "\n", '|'], [' ', ' ', '\\|'], $value);
    }
}
