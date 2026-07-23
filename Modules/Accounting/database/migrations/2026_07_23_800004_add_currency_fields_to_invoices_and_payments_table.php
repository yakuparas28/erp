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
        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('currency_id')->nullable()->after('partner_id'); // null: TL (fonksiyonel)
            $table->decimal('exchange_rate_used', 15, 6)->nullable()->after('currency_id');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('currency_id')->nullable()->after('partner_id');
            $table->decimal('exchange_rate_used', 15, 6)->nullable()->after('currency_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['currency_id', 'exchange_rate_used']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['currency_id', 'exchange_rate_used']);
        });
    }
};
