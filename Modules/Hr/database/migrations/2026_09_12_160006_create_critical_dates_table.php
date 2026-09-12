<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('critical_dates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->text('description')->nullable();
            $table->boolean('block_leave_requests')->default(false);
            $table->timestamps();

            $table->index(['tenant_id', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('critical_dates');
    }
};
