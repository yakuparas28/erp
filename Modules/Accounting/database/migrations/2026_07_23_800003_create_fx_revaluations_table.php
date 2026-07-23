<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fx_revaluations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('invoice_id');
            $table->unsignedBigInteger('payment_id')->nullable(); // yalnızca realized için
            $table->enum('type', ['realized', 'unrealized']);
            $table->decimal('difference_amount', 15, 4); // işaretli: + kâr, - zarar (646/656 yönü buradan türetilir)
            $table->date('revaluation_date');
            $table->timestamps();

            $table->index(['tenant_id', 'invoice_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fx_revaluations');
    }
};
