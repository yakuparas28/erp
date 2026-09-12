<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('key', 40);
            $table->string('name');
            $table->boolean('deducts_from_balance')->default(true);
            $table->boolean('requires_document')->default(false);
            $table->boolean('requires_second_level')->default(false);
            $table->enum('unit', ['day', 'hour', 'half_day'])->default('day');
            $table->unsignedInteger('max_days_per_year')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_types');
    }
};
