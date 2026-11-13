<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\MedicineBatch;
use Database\Seeders\ClinicMedicineSeeder;
use Illuminate\Console\Command;

class ClinicMedicines extends Command
{
    protected $signature = 'clinic:medicines';

    protected $description = 'Bổ sung 3 thuốc có thông tin nguồn, tồn đầu riêng từng cơ sở; giữ nguyên kho và đơn cũ';

    public function handle(ClinicMedicineSeeder $seeder): int
    {
        $seeder->run();
        $this->info("Đã thêm {$seeder->createdMedicines} thuốc, {$seeder->createdBatches} lô; ngừng sử dụng {$seeder->archivedMedicines} thuốc mẫu cũ.");
        foreach ($seeder->warnings as $warning) {
            $this->warn($warning);
        }
        $rows = [];
        foreach (Branch::where('is_active', true)->orderBy('id')->get() as $branch) {
            foreach (config('clinic_medicines.products') as $code => $product) {
                $quantity = MedicineBatch::where('branch_id', $branch->id)->whereHas('medicine', fn ($q) => $q->where('code', $code)->where('is_active', true))->sum('available_qty');
                $rows[] = [$branch->code, $product['name'], $quantity.' '.$product['unit']];
            }
        }
        $this->table(['Cơ sở', 'Thuốc', 'Tồn hiện tại'], $rows);
        $this->line('Tên/hàm lượng theo nhà sản xuất; giá là giá tham khảo. Số lô, hạn dùng, số lượng KHOITAO là dữ liệu khởi tạo, cần thay theo phiếu nhập thực tế.');

        return self::SUCCESS;
    }
}
