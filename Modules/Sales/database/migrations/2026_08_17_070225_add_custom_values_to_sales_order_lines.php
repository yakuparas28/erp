<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Odoo `sale.order.line.custom_values` denkliği: müşteriye özel metin girişi
 * (attribute value `is_custom=true` iken). JSON şeması:
 *   { "<attribute_value_id>": "customer text" }
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_order_lines', function (Blueprint $table): void {
            $table->json('custom_values')->nullable()->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('sales_order_lines', function (Blueprint $table): void {
            $table->dropColumn('custom_values');
        });
    }
};
