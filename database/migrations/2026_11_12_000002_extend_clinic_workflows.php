<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $t) {
            $t->id();
            $t->string('code', 20)->unique();
            $t->string('name');
            $t->string('address')->nullable();
            $t->string('phone', 20)->nullable();
            $t->string('timezone')->default('Asia/Ho_Chi_Minh');
            $t->string('opening_hours')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        $branch = DB::table('branches')->insertGetId(['code' => 'CN01', 'name' => 'Phòng khám An Tâm - Chi nhánh 1', 'timezone' => 'Asia/Ho_Chi_Minh', 'created_at' => now(), 'updated_at' => now()]);
        Schema::create('branch_user', function (Blueprint $t) {
            $t->foreignId('branch_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->primary(['branch_id', 'user_id']);
        });
        Schema::create('user_permissions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->foreignId('branch_id')->constrained()->restrictOnDelete();
            $t->string('permission_code', 60);
            $t->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['user_id', 'branch_id', 'permission_code'], 'user_branch_permission_unique');
        });
        Schema::create('doctor_specialty', function (Blueprint $t) {
            $t->foreignId('doctor_id')->constrained()->restrictOnDelete();
            $t->foreignId('specialty_id')->constrained()->restrictOnDelete();
            $t->primary(['doctor_id', 'specialty_id']);
        });
        Schema::table('users', function (Blueprint $t) {
            $t->string('status', 20)->default('ACTIVE');
        });
        Schema::table('patients', function (Blueprint $t) {
            $t->foreignId('registered_branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            $t->text('medical_history')->nullable();
            $t->string('emergency_contact')->nullable();
            $t->boolean('is_active')->default(true);
            $t->boolean('email_enabled')->default(true);
            $t->timestamp('auto_booking_consented_at')->nullable();
            $t->timestamp('auto_booking_expires_at')->nullable();
        });
        foreach (['doctor_schedules', 'appointments'] as $table) {
            Schema::table($table, function (Blueprint $t) use ($branch) {
                $t->foreignId('branch_id')->default($branch)->constrained()->restrictOnDelete();
            });
        }
        Schema::table('doctor_schedules', function (Blueprint $t) {
            $t->unsignedSmallInteger('capacity')->default(1);
        });
        Schema::table('appointments', function (Blueprint $t) {
            $t->string('idempotency_key', 100)->nullable()->unique();
        });
        Schema::create('doctor_time_off', function (Blueprint $t) {
            $t->id();
            $t->foreignId('doctor_id')->constrained()->restrictOnDelete();
            $t->dateTime('starts_at');
            $t->dateTime('ends_at');
            $t->string('reason');
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
        });
        Schema::create('appointment_history', function (Blueprint $t) {
            $t->id();
            $t->foreignId('appointment_id')->constrained()->restrictOnDelete();
            $t->json('old_values');
            $t->json('new_values');
            $t->string('reason');
            $t->foreignId('changed_by')->constrained('users');
            $t->timestamps();
        });
        Schema::create('visits', function (Blueprint $t) {
            $t->id();
            $t->string('visit_code', 30)->unique();
            $t->foreignId('appointment_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $t->foreignId('patient_id')->constrained()->restrictOnDelete();
            $t->foreignId('doctor_id')->constrained()->restrictOnDelete();
            $t->foreignId('branch_id')->constrained()->restrictOnDelete();
            $t->string('status', 25)->default('WAITING');
            $t->text('reason');
            $t->timestamp('checked_in_at');
            $t->timestamp('started_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
            $t->index(['branch_id', 'status', 'checked_in_at']);
        });
        Schema::table('medical_records', function (Blueprint $t) {
            $t->unsignedBigInteger('appointment_id')->nullable()->change();
        });
        Schema::table('medical_records', function (Blueprint $t) {
            $t->foreignId('visit_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $t->text('findings')->nullable();
            $t->string('status', 20)->default('DRAFT');
            $t->unsignedInteger('version')->default(1);
            $t->timestamp('signed_at')->nullable();
            $t->foreignId('signed_by')->nullable()->constrained('users');
        });
        Schema::create('medical_record_revisions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('medical_record_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('version');
            $t->json('snapshot');
            $t->string('reason');
            $t->foreignId('updated_by')->constrained('users');
            $t->timestamps();
            $t->unique(['medical_record_id', 'version']);
        });
        Schema::create('medicines', function (Blueprint $t) {
            $t->id();
            $t->string('code', 30)->unique();
            $t->string('name');
            $t->string('unit', 30);
            $t->string('strength')->nullable();
            $t->decimal('price', 12, 2)->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('prescriptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('visit_id')->constrained()->restrictOnDelete();
            $t->foreignId('doctor_id')->constrained()->restrictOnDelete();
            $t->string('status', 20)->default('DRAFT');
            $t->boolean('bill_at_clinic')->default(false);
            $t->timestamp('signed_at')->nullable();
            $t->foreignId('signed_by')->nullable()->constrained('users');
            $t->timestamps();
        });
        Schema::create('prescription_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('prescription_id')->constrained()->restrictOnDelete();
            $t->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $t->string('dosage');
            $t->string('route', 100);
            $t->string('frequency', 100);
            $t->unsignedSmallInteger('duration_days');
            $t->unsignedInteger('quantity');
            $t->string('instructions');
            $t->timestamps();
        });
        Schema::create('service_orders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('visit_id')->constrained()->restrictOnDelete();
            $t->foreignId('doctor_id')->constrained()->restrictOnDelete();
            $t->string('status', 25)->default('ORDERED');
            $t->timestamps();
        });
        Schema::create('service_order_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('service_order_id')->constrained()->restrictOnDelete();
            $t->foreignId('service_id')->constrained()->restrictOnDelete();
            $t->string('external_order_code', 60)->unique();
            $t->string('status', 25)->default('ORDERED');
            $t->timestamps();
        });
        Schema::create('diagnostic_results', function (Blueprint $t) {
            $t->id();
            $t->foreignId('service_order_item_id')->constrained()->restrictOnDelete();
            $t->text('value_text');
            $t->string('unit', 40)->nullable();
            $t->string('status', 20)->default('RECEIVED');
            $t->string('source', 40)->default('MANUAL');
            $t->string('attachment_path')->nullable();
            $t->timestamp('verified_at')->nullable();
            $t->foreignId('verified_by')->nullable()->constrained('users');
            $t->timestamps();
        });
        Schema::table('invoices', function (Blueprint $t) {
            $t->unsignedBigInteger('appointment_id')->nullable()->change();
        });
        Schema::table('invoices', function (Blueprint $t) use ($branch) {
            $t->foreignId('visit_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $t->foreignId('branch_id')->default($branch)->constrained()->restrictOnDelete();
        });
        Schema::table('invoice_items', function (Blueprint $t) {
            $t->string('source_type', 30)->nullable();
            $t->unsignedBigInteger('source_id')->nullable();
            $t->unique(['invoice_id', 'source_type', 'source_id'], 'invoice_source_unique');
        });
        Schema::table('payments', function (Blueprint $t) {
            $t->string('status', 20)->default('SUCCEEDED');
            $t->string('idempotency_key', 100)->nullable()->unique();
        });
        Schema::create('refunds', function (Blueprint $t) {
            $t->id();
            $t->foreignId('payment_id')->constrained()->restrictOnDelete();
            $t->decimal('amount', 12, 2);
            $t->string('reason');
            $t->string('idempotency_key', 100)->unique();
            $t->foreignId('approved_by')->constrained('users');
            $t->timestamps();
        });
        Schema::create('medicine_batches', function (Blueprint $t) {
            $t->id();
            $t->foreignId('branch_id')->constrained()->restrictOnDelete();
            $t->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $t->string('lot_number', 60);
            $t->date('expiry_date');
            $t->unsignedInteger('available_qty')->default(0);
            $t->timestamps();
            $t->unique(['branch_id', 'medicine_id', 'lot_number', 'expiry_date'], 'batch_unique');
        });
        Schema::create('goods_receipts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('batch_id')->constrained('medicine_batches')->restrictOnDelete();
            $t->unsignedInteger('quantity');
            $t->string('reference', 100)->unique();
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
        });
        Schema::create('dispensations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('prescription_id')->constrained()->restrictOnDelete();
            $t->foreignId('branch_id')->constrained()->restrictOnDelete();
            $t->string('status', 30);
            $t->string('idempotency_key', 100)->unique();
            $t->foreignId('dispensed_by')->constrained('users');
            $t->timestamps();
        });
        Schema::create('dispensation_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('dispensation_id')->constrained()->restrictOnDelete();
            $t->foreignId('prescription_item_id')->constrained()->restrictOnDelete();
            $t->foreignId('batch_id')->constrained('medicine_batches')->restrictOnDelete();
            $t->unsignedInteger('quantity');
            $t->timestamps();
        });
        Schema::create('stock_movements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('batch_id')->constrained('medicine_batches')->restrictOnDelete();
            $t->integer('quantity_delta');
            $t->string('type', 30);
            $t->string('reference_type', 40);
            $t->unsignedBigInteger('reference_id');
            $t->string('reason')->nullable();
            $t->foreignId('actor_id')->constrained('users');
            $t->timestamps();
        });
        Schema::create('follow_up_plans', function (Blueprint $t) {
            $t->id();
            $t->foreignId('visit_id')->constrained()->restrictOnDelete();
            $t->date('suggested_from');
            $t->date('suggested_to');
            $t->string('reason');
            $t->string('status', 20)->default('PROPOSED');
            $t->foreignId('appointment_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $t->timestamps();
        });
        Schema::create('notification_jobs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('appointment_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('event_key', 150)->unique();
            $t->string('recipient');
            $t->string('template', 60);
            $t->text('body');
            $t->string('status', 20)->default('PENDING');
            $t->unsignedSmallInteger('retry_count')->default(0);
            $t->timestamp('scheduled_at');
            $t->timestamp('sent_at')->nullable();
            $t->string('error_code', 60)->nullable();
            $t->timestamps();
        });
        Schema::create('integration_systems', function (Blueprint $t) {
            $t->id();
            $t->string('code', 40)->unique();
            $t->string('type', 20);
            $t->foreignId('branch_id')->constrained()->restrictOnDelete();
            $t->string('token_hash', 64);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('integration_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('integration_system_id')->constrained()->restrictOnDelete();
            $t->string('external_message_id', 100);
            $t->string('payload_hash', 64);
            $t->string('status', 20)->default('RECEIVED');
            $t->string('error_code', 60)->nullable();
            $t->unsignedBigInteger('resource_id')->nullable();
            $t->timestamps();
            $t->unique(['integration_system_id', 'external_message_id'], 'integration_message_unique');
        });
        Schema::create('device_measurements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('visit_id')->constrained()->restrictOnDelete();
            $t->string('device_code', 60);
            $t->string('measurement_type', 40);
            $t->decimal('value', 8, 2);
            $t->string('unit', 20);
            $t->timestamp('measured_at');
            $t->string('status', 20)->default('RECEIVED');
            $t->foreignId('verified_by')->nullable()->constrained('users');
            $t->timestamps();
        });
        Schema::table('audit_logs', function (Blueprint $t) {
            $t->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('request_id', 40)->nullable();
        });

        // Upgrade existing demo data without deleting appointments, records or money.
        DB::table('patients')->update(['registered_branch_id' => $branch]);
        foreach (DB::table('users')->join('roles', 'roles.id', '=', 'users.role_id')->where('roles.code', '!=', 'patient')->select('users.id')->get() as $user) {
            DB::table('branch_user')->insert(['branch_id' => $branch, 'user_id' => $user->id]);
        }
        foreach (DB::table('doctors')->get() as $doctor) {
            DB::table('doctor_specialty')->insert(['doctor_id' => $doctor->id, 'specialty_id' => $doctor->specialty_id]);
        }
        foreach (DB::table('appointments')->whereIn('status', ['CHECKED_IN', 'IN_PROGRESS', 'COMPLETED'])->get() as $a) {
            $id = DB::table('visits')->insertGetId([
                'visit_code' => 'LKCU'.$a->id, 'appointment_id' => $a->id, 'patient_id' => $a->patient_id, 'doctor_id' => $a->doctor_id,
                'branch_id' => $branch, 'status' => $a->status === 'CHECKED_IN' ? 'WAITING' : $a->status,
                'reason' => $a->reason, 'checked_in_at' => $a->checked_in_at ?? $a->created_at, 'created_at' => $a->created_at, 'updated_at' => now(),
                'completed_at' => $a->status === 'COMPLETED' ? $a->updated_at : null,
            ]);
            DB::table('medical_records')->where('appointment_id', $a->id)->update(['visit_id' => $id, 'status' => $a->status === 'COMPLETED' ? 'SIGNED' : 'DRAFT']);
            DB::table('invoices')->where('appointment_id', $a->id)->update(['visit_id' => $id]);
        }
        DB::table('appointments')->where('status', 'IN_PROGRESS')->update(['status' => 'CHECKED_IN']);
        DB::table('invoices')->whereIn('status', ['UNPAID', 'PAID'])->update(['status' => 'ISSUED']);
    }

    public function down(): void
    {
        // Forward-only: rolling this migration back would discard medical and financial history.
        throw new RuntimeException('Migration nghiệp vụ chỉ nâng cấp. Khôi phục bản sao lưu để quay lại phiên bản cũ.');
    }
};
