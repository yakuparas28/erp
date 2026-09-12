<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_hour_configs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->cascadeOnDelete();
            $table->decimal('daily_work_hours', 4, 2)->default(8);
            $table->decimal('monthly_leave_hours', 5, 2)->default(24);
            $table->timestamps();

            $table->unique(['tenant_id', 'department_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_hour_configs');
    }
};
