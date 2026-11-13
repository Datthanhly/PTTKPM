<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ClinicUpgradeSeeder extends Seeder
{
    public function run(): void
    {
        if (AuditLog::where('action', 'seed.demo_workflows_added')->exists()) {
            return;
        }
        $branch = Branch::where('code', 'CN01')->firstOrFail();
        $branch2 = Branch::firstOrCreate(['code' => 'CN02'], ['name' => 'Phòng khám An Tâm - Chi nhánh 2', 'address' => 'Địa chỉ mô phỏng']);
        $admin = User::where('email', 'admin@clinic.test')->firstOrFail();
        $doctor = User::where('email', 'bacsi.an@clinic.test')->first();
        if ($doctor) {
            foreach (['LAB_RESULT_ENTER', 'LAB_RESULT_VERIFY', 'MEDICINE_DISPENSE'] as $permission) {
                DB::table('user_permissions')->updateOrInsert(
                    ['user_id' => $doctor->id, 'branch_id' => $branch->id, 'permission_code' => $permission], ['granted_by' => $admin->id, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
        foreach (['receptionist' => 'letan.cn2@clinic.test', 'cashier' => 'thungan.cn2@clinic.test'] as $role => $email) {
            $u = User::firstOrCreate(['email' => $email], ['role_id' => DB::table('roles')->where('code', $role)->value('id'), 'name' => 'Nhân viên demo CN2', 'password' => 'Password@123']);
            $u->branches()->syncWithoutDetaching([$branch2->id]);
        }
        foreach (['DEMO-A', 'DEMO-B'] as $code) {
            $m = Medicine::firstOrCreate(['code' => $code], ['name' => 'Thuốc mô phỏng '.$code, 'unit' => 'viên', 'strength' => 'Nhãn minh họa', 'price' => 1000]);
            if (! MedicineBatch::where('medicine_id', $m->id)->exists()) {
                $b = MedicineBatch::create(['branch_id' => $branch->id, 'medicine_id' => $m->id, 'lot_number' => 'LO-DEMO-01', 'expiry_date' => today()->addYear(), 'available_qty' => 100]);
                DB::table('stock_movements')->insert(['batch_id' => $b->id, 'quantity_delta' => 100, 'type' => 'OPENING', 'reference_type' => 'medicine_batches', 'reference_id' => $b->id,
                    'actor_id' => $admin->id, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
        AuditLog::record('seed.demo_workflows_added', $admin);
    }
}
