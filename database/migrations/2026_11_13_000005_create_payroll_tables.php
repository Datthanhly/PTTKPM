<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->string('pay_basis', 10);
            $table->decimal('unit_rate', 16, 0);
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('payroll_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->date('pay_month');
            $table->string('employee_name');
            $table->string('role_name');
            $table->string('pay_basis', 10);
            $table->decimal('unit_rate', 16, 0);
            $table->unsignedInteger('worked_minutes')->default(0);
            $table->unsignedInteger('worked_shifts')->default(0);
            $table->decimal('base_amount', 16, 0);
            $table->decimal('allowance', 16, 0)->default(0);
            $table->decimal('bonus', 16, 0)->default(0);
            $table->decimal('deduction', 16, 0)->default(0);
            $table->decimal('net_amount', 16, 0);
            $table->json('source_shifts');
            $table->timestamp('calculated_at');
            $table->string('adjustment_reason', 1000)->nullable();
            $table->string('note', 1000)->nullable();
            $table->string('status', 10)->default('UNPAID');
            $table->foreignId('paid_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_reference', 200)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'pay_month']);
            $table->index(['pay_month', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_entries');
        Schema::dropIfExists('salary_profiles');
    }
};
