<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_task_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('critical_window_enabled')->default(true);
            $table->unsignedSmallInteger('critical_window_days')->default(7);
            $table->unsignedSmallInteger('mtv_reminder_days')->default(30);
            $table->timestamp('critical_window_last_run_at')->nullable();
            $table->unsignedInteger('critical_window_last_run_notified')->default(0);
            $table->timestamp('mtv_last_run_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_task_settings');
    }
};
