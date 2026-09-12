<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Odoo `stock.quant.owner_id` denkliği: bir quant kime ait olduğu bilgisiyle
 * tutulabilir (konsinye stok). owner_partner_id null → firmaya ait.
 * Bu, stok fiziksel olarak bizde ama mülkiyeti başkasında olan durumu
 * (konsinye) izler.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_quants', function (Blueprint $table): void {
            $table->foreignId('owner_partner_id')->nullable()->after('lot_id')
                ->constrained('partners')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_quants', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('owner_partner_id');
        });
    }
};
