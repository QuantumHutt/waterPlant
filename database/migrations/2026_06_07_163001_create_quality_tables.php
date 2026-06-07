<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('water_quality_tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_batch_id')->nullable()->constrained('production_batches')->nullOnDelete();
            $table->decimal('tds_value', 8, 2)->nullable();   // ppm
            $table->decimal('ph_value', 5, 2)->nullable();
            $table->decimal('temperature', 5, 2)->nullable();
            $table->boolean('passed')->default(true);
            $table->foreignId('tested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('tested_at');
            $table->string('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('maintenance_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('asset', 30);  // filter|ro_membrane|uv|pump
            $table->string('title');
            $table->unsignedInteger('frequency_days')->default(30);
            $table->date('last_done_at')->nullable();
            $table->date('next_due_at')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('maintenance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_schedule_id')->constrained('maintenance_schedules')->cascadeOnDelete();
            $table->date('done_at');
            $table->foreignId('done_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('cost', 18, 2)->default(0);
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_logs');
        Schema::dropIfExists('maintenance_schedules');
        Schema::dropIfExists('water_quality_tests');
    }
};
