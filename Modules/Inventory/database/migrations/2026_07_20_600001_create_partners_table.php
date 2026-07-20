<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');
            $table->string('tax_number')->nullable();
            $table->boolean('is_customer')->default(false);
            $table->boolean('is_supplier')->default(false);
            $table->unsignedInteger('payment_term_days')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'is_customer']);
            $table->index(['tenant_id', 'is_supplier']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partners');
    }
};
