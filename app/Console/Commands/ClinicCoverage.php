<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Doctor;
use App\Models\Specialty;
use App\Models\User;
use App\Services\DoctorCoverageService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

class ClinicCoverage extends Command
{
    protected $signature = 'clinic:coverage {--from= : Ngày bắt đầu YYYY-MM-DD, mặc định ngày mai}
        {--days=14 : Số ngày, phạm vi tối đa 90 ngày tới}
        {--include-sunday : Xếp cả Chủ nhật}
        {--apply : Lưu dữ liệu, bỏ tùy chọn này để chỉ xem trước}
        {--report : Sau khi lưu, xuất báo cáo và cập nhật danh sách tài khoản}';

    protected $description = 'Bổ sung ít nhất 2 bác sĩ/khoa/cơ sở và phân ca sáng/chiều, giữ các ca đã có lịch hẹn';

    public function handle(DoctorCoverageService $coverage): int
    {
        $days = filter_var($this->option('days'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 90]]);
        $from = $this->option('from') ?: today()->addDay()->format('Y-m-d');
        try {
            $parsed = Carbon::createFromFormat('!Y-m-d', $from);
            if ($days === false || $parsed->format('Y-m-d') !== $from) {
                throw new \InvalidArgumentException;
            }
        } catch (\Throwable) {
            $this->error('Dùng --from=YYYY-MM-DD và --days là số nguyên từ 1 đến 90.');

            return self::FAILURE;
        }
        $admin = User::where('is_active', true)->where('status', 'ACTIVE')->whereHas('role', fn ($q) => $q->where('code', 'admin'))->orderBy('id')->first();
        if (! $admin) {
            $this->error('Cần tài khoản admin đang hoạt động để ghi lịch sử phân ca.');

            return self::FAILURE;
        }
        $previousActor = Auth::user();
        Auth::setUser($admin);
        $level = DB::transactionLevel();
        DB::beginTransaction();
        try {
            $result = $coverage->arrange($from, $days, (bool) $this->option('include-sunday'));
            if ($this->option('apply')) {
                DB::commit();
            } else {
                DB::rollBack($level);
            }
        } catch (ValidationException $error) {
            DB::rollBack($level);
            $this->error(implode(' ', $error->validator->errors()->all()));

            return self::FAILURE;
        } catch (\Throwable $error) {
            DB::rollBack($level);
            throw $error;
        } finally {
            if ($previousActor) {
                Auth::setUser($previousActor);
            } else {
                Auth::forgetUser();
            }
        }
        $this->info(($this->option('apply') ? 'Đã lưu' : 'XEM TRƯỚC, chưa lưu').': '.$result['from'].' đến '.$result['until'].($result['include_sunday'] ? ', có Chủ nhật.' : ', nghỉ Chủ nhật.'));
        $this->table(['Bác sĩ mới', 'Ca mới', 'Ca trống đổi bác sĩ', 'Ca trống dư đóng', 'Ca có tham chiếu giữ nguyên', 'Ô khoa/cơ sở/buổi đã xếp'], [[
            count($result['created_doctors']), $result['created_shifts'], $result['reassigned_shifts'], $result['closed_empty_shifts'], $result['protected_shifts'], $result['checked_cells'],
        ]]);
        if ($this->option('apply') && $this->option('report')) {
            $this->report($result);
        }

        return self::SUCCESS;
    }

    private function report(array $result): void
    {
        $branches = Branch::where('is_active', true)->orderBy('id')->get();
        $specialties = Specialty::where('is_active', true)->orderBy('id')->get();
        $doctors = Doctor::with(['user.branches', 'user.salaryProfile', 'specialty'])->where('is_active', true)
            ->whereHas('user', fn ($q) => $q->where('is_active', true)->where('status', 'ACTIVE')->whereHas('role', fn ($r) => $r->where('code', 'doctor')))->get();
        $lines = ['# Lịch bác sĩ và độ phủ chuyên khoa', '',
            'Đợt xếp lịch: **'.$result['from'].' đến '.$result['until'].'**; '.($result['include_sunday'] ? 'có Chủ nhật' : 'nghỉ Chủ nhật').'.', '',
            'Mỗi khoa tại mỗi cơ sở có ít nhất 2 bác sĩ. Mỗi buổi sáng 08:00–11:30 và chiều 13:30–17:00 có ít nhất 1 bác sĩ của khoa đó trực tại cơ sở.', '',
            'Chỉ luân phiên ca tương lai còn trống theo tổng giờ trong tuần. Ca đã có lịch hẹn (kể cả lịch sử), đề nghị đổi ca, ca tùy chỉnh/đã đóng và lịch quá khứ giữ nguyên. Ca dư còn trống được đóng, không xóa. Không chuyển bác sĩ hoặc giờ của lịch hẹn bệnh nhân.', '',
            '| Cơ sở | Chuyên khoa | Số bác sĩ |', '|---|---|---|'];
        foreach ($branches as $branch) {
            foreach ($specialties as $specialty) {
                $count = $doctors->filter(fn ($d) => $d->specialty_id === $specialty->id && $d->user->branches->contains('id', $branch->id))->count();
                $lines[] = '| '.$this->cell($branch->name).' | '.$this->cell($specialty->name).' | '.$count.' |';
            }
        }
        $lines = [...$lines, '', '## Chạy lại hoặc mở rộng thời gian', '', '```powershell',
            'php artisan clinic:coverage --days=14 --include-sunday', '# Xem trước, sau đó lưu:',
            'php artisan clinic:backup', 'php artisan clinic:coverage --days=14 --include-sunday --apply --report', '```', '',
            'Có thể chỉ định --from=YYYY-MM-DD và --days=1..90, trong phạm vi 90 ngày tới. Bỏ --include-sunday để nghỉ Chủ nhật. Chạy lại không thêm bác sĩ khi đã đủ; không tự đổi mật khẩu, mức lương cũ hoặc bảng lương. Nếu không thể phủ đủ do lịch nghỉ/ca cố định/xung đột, cả đợt bị rollback và báo ô còn thiếu. Không tự chạy hằng ngày; cần chạy lại để mở rộng lịch sau ngày cuối của đợt này.', '',
            'Bác sĩ mới có đơn giá 500.000 VNĐ/giờ; mật khẩu tài khoản mới là Password@123. [Danh sách nhân sự](Clinic_Demo_Accounts.md).', ''];
        File::put(base_path('document/Doctor_Coverage_Report.md'), implode(PHP_EOL, $lines));
        $accounts = ['# Danh sách nhân sự demo phòng khám', '',
            'Dữ liệu giả lập phục vụ đồ án. Mỗi chuyên khoa tại mỗi cơ sở đang hoạt động có ít nhất 2 bác sĩ; mỗi cơ sở có 2 lễ tân và 2 thu ngân.', '',
            '**Tài khoản mới dùng mật khẩu `Password@123`. Mật khẩu tài khoản cũ giữ nguyên; danh sách này không xác nhận mật khẩu cũ.**', '',
            '[Đợt phân ca, số lượng từng khoa/cơ sở và lệnh mở rộng lịch](Doctor_Coverage_Report.md).', '',
            '## Đơn giá lương', '', 'Bác sĩ mới: 500.000 VNĐ/giờ. Lễ tân/thu ngân đã được đặt 200.000 VNĐ/giờ. Admin có thể chỉnh riêng từng người; mức lương cũ và bảng lương đã lập không bị đổi khi phân ca. [Hướng dẫn tiền lương](Payroll_Workflow.md).', '',
            '## Tài khoản', '', '| Họ tên | Vai trò | Chuyên khoa | Cơ sở | Email |', '|---|---|---|---|---|'];
        $users = User::with(['role', 'branches', 'doctor.specialty'])->where('email', 'like', '%@clinic.test')
            ->whereHas('role', fn ($q) => $q->whereIn('code', ['doctor', 'receptionist', 'cashier']))->orderBy('role_id')->orderBy('name')->get();
        foreach ($users as $user) {
            $cells = [$user->name, $user->role->name, $user->doctor?->specialty?->name ?? '—', $user->branches->pluck('name')->join(', '), $user->email];
            $accounts[] = '| '.implode(' | ', array_map(fn ($v) => $this->cell($v), $cells)).' |';
        }
        File::put(base_path('document/Clinic_Demo_Accounts.md'), implode(PHP_EOL, $accounts).PHP_EOL);
        $this->info('Báo cáo: document/Doctor_Coverage_Report.md; tài khoản: document/Clinic_Demo_Accounts.md');
    }

    private function cell(string $value): string
    {
        return str_replace(["\r", "\n", '|'], [' ', ' ', '\\|'], $value);
    }
}
