<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_transfer_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_schedule_id')->constrained('doctor_schedules')->restrictOnDelete();
            $table->foreignId('sender_doctor_id')->constrained('doctors')->restrictOnDelete();
            $table->foreignId('receiver_doctor_id')->constrained('doctors')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->date('work_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('status', 20)->default('PENDING');
            $table->unsignedBigInteger('pending_key')->nullable()->unique();
            $table->string('note', 1000);
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
            $table->index(['receiver_doctor_id', 'status']);
            $table->index(['sender_doctor_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_transfer_requests');
    }
};
