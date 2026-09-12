<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('title')->nullable();
            $table->date('hire_date')->nullable();
            $table->date('birth_date')->nullable();
            $table->date('termination_date')->nullable();
            $table->string('national_id', 20)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('mobile', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->decimal('annual_leave_balance', 6, 2)->default(0);
            $table->boolean('second_level_approval_required')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
            $table->index(['tenant_id', 'department_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
