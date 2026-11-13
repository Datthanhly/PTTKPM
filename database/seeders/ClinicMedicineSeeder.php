<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ClinicMedicineSeeder extends Seeder
{
    public int $createdMedicines = 0;

    public int $createdBatches = 0;

    public int $archivedMedicines = 0;

    public array $warnings = [];

    public function run(): void
    {
        $this->createdMedicines = $this->createdBatches = $this->archivedMedicines = 0;
        $this->warnings = [];
        DB::transaction(function () {
            $branches = Branch::where('is_active', true)->orderBy('id')->lockForUpdate()->get();
            $actor = User::where('is_active', true)->where('status', 'ACTIVE')->whereHas('role', fn ($q) => $q->where('code', 'admin'))->orderBy('id')->first();
            if ($branches->isEmpty() || ! $actor) {
                throw new RuntimeException('Cần cơ sở hoạt động và tài khoản quản trị để ghi nhận tồn đầu.');
            }
            foreach (config('clinic_medicines.products') as $code => $product) {
                $medicine = Medicine::firstOrCreate(['code' => $code], [
                    'name' => $product['name'], 'unit' => $product['unit'], 'strength' => $product['strength'], 'price' => $product['price'], 'is_active' => true,
                ]);
                if ($medicine->wasRecentlyCreated) {
                    $this->createdMedicines++;
                    AuditLog::record('seed.medicine_added', $medicine, [], ['source_url' => $product['source_url'], 'price_source_url' => $product['price_source_url'], 'checked_on' => config('clinic_medicines.checked_on')]);
                }
                // Preserve administrator edits and never put stock under a different product identity.
                if (! $medicine->is_active || $medicine->name !== $product['name'] || $medicine->unit !== $product['unit'] || $medicine->strength !== $product['strength']) {
                    $this->warnings[] = $code.': giữ cấu hình hiện có, không tự tạo tồn đầu.';

                    continue;
                }
                foreach ($branches as $branch) {
                    $reference = 'KHOITAO-CATALOG-V1-'.$branch->id.'-'.$code;
                    if (DB::table('goods_receipts')->where('reference', $reference)->exists()
                        || MedicineBatch::where('branch_id', $branch->id)->where('medicine_id', $medicine->id)->exists()) {
                        continue;
                    }
                    $quantity = (int) $product['opening_quantity'];
                    $batch = MedicineBatch::create([
                        'branch_id' => $branch->id, 'medicine_id' => $medicine->id,
                        'lot_number' => 'KHOITAO-'.$branch->code.'-'.$code.'-01',
                        'expiry_date' => today()->addYear(), 'available_qty' => $quantity,
                    ]);
                    $receiptId = DB::table('goods_receipts')->insertGetId([
                        'batch_id' => $batch->id, 'quantity' => $quantity, 'reference' => $reference,
                        'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                    DB::table('stock_movements')->insert([
                        'batch_id' => $batch->id, 'quantity_delta' => $quantity, 'type' => 'RECEIPT',
                        'reference_type' => 'goods_receipts', 'reference_id' => $receiptId,
                        'reason' => 'Dữ liệu tồn đầu phục vụ đồ án; số lô và hạn dùng cần đối chiếu phiếu nhập thực tế.',
                        'actor_id' => $actor->id, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                    AuditLog::record('seed.medicine_opening_stock_added', $batch, [], ['quantity' => $quantity, 'receipt_id' => $receiptId]);
                    $this->createdBatches++;
                }
            }
            // Archive only the untouched placeholder identities; preserve all lots and clinical links.
            foreach (['DEMO-A', 'DEMO-B'] as $code) {
                $medicine = Medicine::where('code', $code)->where('name', 'Thuốc mô phỏng '.$code)->where('strength', 'Nhãn minh họa')->where('is_active', true)->first();
                if ($medicine) {
                    $medicine->update(['is_active' => false]);
                    AuditLog::record('seed.placeholder_medicine_archived', $medicine, ['is_active' => true], ['is_active' => false]);
                    $this->archivedMedicines++;
                }
            }
        });
    }
}
