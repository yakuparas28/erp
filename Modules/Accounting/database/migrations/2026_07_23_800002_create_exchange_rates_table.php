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
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('currency_id');
            $table->date('rate_date');
            $table->decimal('buy_rate', 15, 6);
            $table->decimal('sell_rate', 15, 6);
            $table->enum('source', ['tcmb', 'manual']);
            $table->timestamps();

            $table->unique(['tenant_id', 'currency_id', 'rate_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
