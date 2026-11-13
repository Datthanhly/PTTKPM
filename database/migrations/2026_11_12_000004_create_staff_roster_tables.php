<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_schedule_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('role_code', 30);
            $table->date('work_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('status', 20)->default('WAITING');
            $table->char('active_key', 64)->nullable()->unique();
            $table->string('note', 1000)->nullable();
            $table->string('decision_note', 1000)->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'work_date', 'status'], 'staff_request_branch_date_status');
            $table->index(['user_id', 'work_date']);
        });
        Schema::create('staff_work_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->unique()->constrained('staff_schedule_requests')->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('role_code', 30);
            $table->date('work_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('status', 20)->default('DRAFT');
            $table->string('note', 1000)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'work_date', 'status'], 'staff_shift_branch_date_status');
            $table->index(['user_id', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_work_shifts');
        Schema::dropIfExists('staff_schedule_requests');
    }
};
