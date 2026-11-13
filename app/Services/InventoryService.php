<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Dispensation;
use App\Models\MedicineBatch;
use App\Models\Prescription;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    public function receive(array $data): MedicineBatch
    {
        ClinicAccess::require('INVENTORY_MANAGE', (int) $data['branch_id']);

        return DB::transaction(function () use ($data) {
            // Serialize new lots as well as existing ones.
            DB::table('branches')->where('id', $data['branch_id'])->update(['updated_at' => DB::raw('updated_at')]);
            abort_if(DB::table('goods_receipts')->where('reference', $data['reference'])->exists(), 409, 'Số phiếu nhập đã sử dụng.');
            $batch = MedicineBatch::firstOrCreate(collect($data)->only(['branch_id', 'medicine_id', 'lot_number', 'expiry_date'])->all(), ['available_qty' => 0]);
            $receipt = DB::table('goods_receipts')->insertGetId(['batch_id' => $batch->id, 'quantity' => $data['quantity'], 'reference' => $data['reference'],
                'created_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
            $batch->increment('available_qty', $data['quantity']);
            $this->movement($batch->id, $data['quantity'], 'RECEIPT', 'goods_receipts', $receipt);
            AuditLog::record('inventory.received', $batch, [], ['quantity' => $data['quantity'], 'receipt_id' => $receipt]);

            return $batch;
        }, 5);
    }

    public function dispense(Prescription $prescription, array $quantities, string $key): Dispensation
    {
        $branchId = $prescription->visit->branch_id;
        ClinicAccess::require('MEDICINE_DISPENSE', $branchId);

        return DB::transaction(function () use ($prescription, $quantities, $key, $branchId) {
            DB::table('prescriptions')->where('id', $prescription->id)->update(['updated_at' => DB::raw('updated_at')]);
            $p = Prescription::lockForUpdate()->findOrFail($prescription->id);
            if ($old = Dispensation::where('idempotency_key', $key)->first()) {
                $given = $old->items()->get()->groupBy('prescription_item_id')->map(fn ($rows) => (int) $rows->sum('quantity'))->all();
                $requested = array_map('intval', array_filter($quantities, fn ($qty) => (int) $qty > 0));
                ksort($given);
                ksort($requested);
                abort_unless($old->prescription_id === $p->id && $given === $requested, 409, 'Khóa cấp phát đã dùng cho nội dung khác.');

                return $old;
            }
            abort_unless($p->status === 'SIGNED' && $p->visit->status !== 'CANCELLED', 422, 'Chỉ cấp phát đơn đã ký và lượt khám hợp lệ.');
            $items = $p->items()->orderBy('medicine_id')->get()->keyBy('id');
            abort_if(array_diff(array_keys($quantities), $items->keys()->all()), 422, 'Dòng thuốc không thuộc đơn.');
            abort_unless(collect($quantities)->sum() > 0, 422, 'Chọn ít nhất một lượng cấp phát.');
            $d = Dispensation::create(['prescription_id' => $p->id, 'branch_id' => $branchId, 'status' => 'PARTIALLY_DISPENSED', 'idempotency_key' => $key, 'dispensed_by' => auth()->id()]);
            foreach ($items as $item) {
                $qty = (int) ($quantities[$item->id] ?? 0);
                if ($qty <= 0) {
                    continue;
                }
                $already = $item->dispensations()->sum('quantity');
                abort_if($qty > $item->quantity - $already, 422, 'Lượng cấp vượt lượng còn lại trong đơn.');
                $remaining = $qty;
                $batches = MedicineBatch::where('branch_id', $branchId)->where('medicine_id', $item->medicine_id)
                    ->whereDate('expiry_date', '>=', today())->where('available_qty', '>', 0)->orderBy('expiry_date')->orderBy('id')->lockForUpdate()->get();
                foreach ($batches as $batch) {
                    $take = min($remaining, $batch->available_qty);
                    $updated = MedicineBatch::whereKey($batch->id)->where('available_qty', '>=', $take)->decrement('available_qty', $take);
                    abort_unless($updated === 1, 409, 'Tồn kho vừa thay đổi. Vui lòng thử lại.');
                    $line = $d->items()->create(['prescription_item_id' => $item->id, 'batch_id' => $batch->id, 'quantity' => $take]);
                    $this->movement($batch->id, -$take, 'DISPENSE', 'dispensation_items', $line->id);
                    $remaining -= $take;
                    if ($remaining === 0) {
                        break;
                    }
                }
                abort_if($remaining > 0, 422, 'Không đủ thuốc còn hạn tại chi nhánh. Giao dịch đã được hoàn tác.');
            }
            $complete = $p->items->every(fn ($i) => $i->dispensations()->sum('quantity') >= $i->quantity);
            $d->update(['status' => $complete ? 'DISPENSED' : 'PARTIALLY_DISPENSED']);
            AuditLog::record('inventory.dispensed', $d, [], ['prescription_id' => $p->id]);

            return $d;
        }, 5);
    }

    public function adjust(MedicineBatch $batch, int $delta, string $reason): void
    {
        ClinicAccess::require('INVENTORY_MANAGE', $batch->branch_id);
        DB::transaction(function () use ($batch, $delta, $reason) {
            abort_unless($delta !== 0, 422);
            $query = MedicineBatch::whereKey($batch->id);
            $changed = $delta < 0 ? $query->where('available_qty', '>=', -$delta)->decrement('available_qty', -$delta) : $query->increment('available_qty', $delta);
            abort_unless($changed === 1, 422, 'Điều chỉnh gây âm kho.');
            $this->movement($batch->id, $delta, 'ADJUSTMENT', 'medicine_batches', $batch->id, $reason);
            AuditLog::record('inventory.adjusted', $batch, [], ['delta' => $delta, 'reason' => $reason]);
        }, 5);
    }

    private function movement(int $batch, int $delta, string $type, string $referenceType, int $referenceId, ?string $reason = null): void
    {
        DB::table('stock_movements')->insert(['batch_id' => $batch, 'quantity_delta' => $delta, 'type' => $type, 'reference_type' => $referenceType,
            'reference_id' => $referenceId, 'reason' => $reason, 'actor_id' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
    }
}
