<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('name');
            $table->boolean('is_recurring_yearly')->default(false);
            $table->timestamps();

            $table->unique(['tenant_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
