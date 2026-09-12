<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_balances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->decimal('carried_from_previous', 6, 2)->default(0);
            $table->decimal('current_year_entitlement', 6, 2)->default(0);
            $table->decimal('manual_adjustment', 6, 2)->default(0);
            $table->text('adjustment_reason')->nullable();
            $table->foreignId('adjusted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_balances');
    }
};
