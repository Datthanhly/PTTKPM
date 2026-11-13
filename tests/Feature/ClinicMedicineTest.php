<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Doctor;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\User;
use App\Models\Visit;
use App\Services\InventoryService;
use Database\Seeders\ClinicMedicineSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClinicMedicineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Branch::create(['code' => '003', 'name' => 'Cơ sở 3']);
        $this->loginAs('admin@clinic.test');
    }

    private function loginAs(string $email): void
    {
        $user = User::where('email', $email)->firstOrFail();
        $this->actingAs($user)->withSession(['password_hash_web' => $user->password]);
    }

    public function test_three_products_have_separate_traceable_stock_at_each_branch_and_reruns_do_not_replenish(): void
    {
        $oldBatches = MedicineBatch::orderBy('id')->get()->toArray();
        $old = Medicine::where('code', 'DEMO-A')->firstOrFail();
        $doctor = Doctor::firstOrFail();
        $visit = Visit::create(['visit_code' => 'OLD-STOCK-RX', 'patient_id' => Patient::firstOrFail()->id, 'doctor_id' => $doctor->id,
            'branch_id' => Branch::where('code', 'CN01')->value('id'), 'status' => 'COMPLETED', 'reason' => 'Lịch sử kiểm thử', 'checked_in_at' => now()]);
        $prescription = Prescription::create(['visit_id' => $visit->id, 'doctor_id' => $doctor->id, 'status' => 'SIGNED', 'signed_by' => $doctor->user_id, 'signed_at' => now()]);
        $item = $prescription->items()->create(['medicine_id' => $old->id, 'dosage' => 'Kiểm thử', 'route' => 'Kiểm thử', 'frequency' => 'Kiểm thử', 'duration_days' => 1, 'quantity' => 1, 'instructions' => 'Dữ liệu kiểm thử']);
        $history = $item->fresh()->toArray();
        $seeder = app(ClinicMedicineSeeder::class);
        $seeder->run();
        $this->assertSame(3, $seeder->createdMedicines);
        $this->assertSame(9, $seeder->createdBatches);
        $this->assertSame(2, $seeder->archivedMedicines);
        $this->assertSame($oldBatches, MedicineBatch::whereIn('id', array_column($oldBatches, 'id'))->orderBy('id')->get()->toArray());
        $this->assertFalse($old->fresh()->is_active);
        $this->assertSame('Thuốc mô phỏng DEMO-A', $old->fresh()->name);
        $this->assertSame($history, $item->fresh()->toArray());
        $this->assertSame('SIGNED', $prescription->fresh()->status);
        foreach (Branch::all() as $branch) {
            $batches = MedicineBatch::where('branch_id', $branch->id)->whereHas('medicine', fn ($q) => $q->where('is_active', true))->get();
            $this->assertCount(3, $batches);
            $this->assertSame(600, (int) $batches->sum('available_qty'));
            foreach ($batches as $batch) {
                $this->assertSame((int) $batch->available_qty, (int) DB::table('stock_movements')->where('batch_id', $batch->id)->sum('quantity_delta'));
                $this->assertDatabaseHas('goods_receipts', ['batch_id' => $batch->id, 'quantity' => $batch->available_qty]);
            }
        }
        $product = Medicine::where('code', 'PANADOL-500')->firstOrFail();
        $product->update(['price' => 1250]);
        $batch = MedicineBatch::where('medicine_id', $product->id)->firstOrFail();
        app(InventoryService::class)->adjust($batch, -5, 'Kiểm thử tồn không bị nạp lại');
        $batch->update(['expiry_date' => today()->addDays(45)]);
        $expiry = $batch->fresh()->expiry_date->format('Y-m-d');
        $counts = [Medicine::count(), MedicineBatch::count(), DB::table('goods_receipts')->count(), DB::table('stock_movements')->count()];
        $this->travel(2)->days();
        $seeder->run();
        $this->assertSame(0, $seeder->createdBatches);
        $this->assertSame(0, $seeder->createdMedicines);
        $this->assertSame(0, $seeder->archivedMedicines);
        $this->assertSame($counts, [Medicine::count(), MedicineBatch::count(), DB::table('goods_receipts')->count(), DB::table('stock_movements')->count()]);
        $this->assertSame(295, (int) $batch->fresh()->available_qty);
        $this->assertSame($expiry, $batch->fresh()->expiry_date->format('Y-m-d'));
        $this->assertSame('1250.00', $product->fresh()->price);
    }

    public function test_default_inventory_hides_archived_products_and_branch_permissions_remain_enforced(): void
    {
        $this->artisan('clinic:medicines')->assertSuccessful();
        $this->get('/kho')->assertOk()->assertSee('Panadol 500 mg')->assertSee('Loratadin 10 mg')->assertSee('Vitamin C 1000 mg')
            ->assertSee('Thông tin từ nhà sản xuất')->assertDontSee('Thuốc mô phỏng DEMO-A')->assertViewHas('batches', fn ($batches) => $batches->total() === 9);
        $this->get('/kho?inactive=1')->assertOk()->assertSee('Thuốc mô phỏng DEMO-A')->assertViewHas('batches', fn ($batches) => $batches->total() === 11);
        $branch3 = Branch::where('code', '003')->value('id');
        $this->get('/kho?branch_id='.$branch3)->assertOk()->assertViewHas('batches', fn ($batches) => $batches->total() === 3 && $batches->every(fn ($b) => $b->branch_id === $branch3));
        $this->loginAs('bacsi.an@clinic.test');
        $this->get('/kho?inactive=1')->assertOk()->assertDontSee('Thuốc mô phỏng DEMO-A')->assertViewHas('batches', fn ($batches) => $batches->total() === 3);
        $this->get('/kho?branch_id='.$branch3)->assertOk()->assertViewHas('batches', fn ($batches) => $batches->total() === 0);
        $this->loginAs('thungan@clinic.test');
        $this->get('/kho')->assertForbidden();
    }

    public function test_reused_codes_and_existing_stock_are_preserved_without_creating_misidentified_lots(): void
    {
        $custom = Medicine::create(['code' => 'CLANOZ-10', 'name' => 'Thuốc quản trị viên đã nhập', 'unit' => 'hộp', 'strength' => 'Giữ cấu hình riêng', 'price' => 12345]);
        $data = $custom->fresh()->toArray();
        $seeder = app(ClinicMedicineSeeder::class);
        $seeder->run();
        $this->assertSame($data, $custom->fresh()->toArray());
        $this->assertSame(0, MedicineBatch::where('medicine_id', $custom->id)->count());
        $this->assertCount(1, $seeder->warnings);
        $this->assertSame(6, $seeder->createdBatches);
        $product = Medicine::where('code', 'PANADOL-500')->firstOrFail();
        $product->update(['is_active' => false]);
        $seeder->run();
        $this->assertFalse($product->fresh()->is_active);
        $this->assertSame(0, $seeder->createdBatches);
    }
}
