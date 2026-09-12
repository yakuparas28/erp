<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->restrictOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->enum('half_day_type', ['morning', 'afternoon'])->nullable();
            $table->decimal('total_days', 5, 2);
            $table->date('return_to_work_date')->nullable();
            $table->text('reason')->nullable();
            $table->boolean('travel_allowance_requested')->default(false);
            $table->string('ticket_no', 50)->nullable();
            $table->string('document_path')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'employee_id']);
            $table->index(['tenant_id', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};
