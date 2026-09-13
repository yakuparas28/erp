<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_calendar_blocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->enum('block_type', ['bakim', 'muayene', 'bloke'])->default('bloke');
            $table->date('start_date');
            $table->date('end_date');
            $table->text('aciklama')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'vehicle_id', 'start_date', 'end_date'], 'idx_blocks_vehicle_range');
            $table->index('block_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_calendar_blocks');
    }
};
