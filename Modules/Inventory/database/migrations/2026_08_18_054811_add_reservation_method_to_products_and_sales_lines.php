<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Odoo `product.template.reservation_method` denkliği:
 *   - at_confirmation: SO onayında otomatik rezerv (varsayılan, mevcut davranış)
 *   - manual: sadece kullanıcı elle rezerv edince
 * SO satırında `reserved_qty` tutuluyor ki manual rezerv edilen miktar bilinir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->enum('reservation_method', ['at_confirmation', 'manual'])->default('at_confirmation')->after('cost_method');
        });

        Schema::table('sales_order_lines', function (Blueprint $table): void {
            $table->decimal('reserved_qty', 15, 4)->default('0')->after('delivered_qty');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('reservation_method');
        });

        Schema::table('sales_order_lines', function (Blueprint $table): void {
            $table->dropColumn('reserved_qty');
        });
    }
};
