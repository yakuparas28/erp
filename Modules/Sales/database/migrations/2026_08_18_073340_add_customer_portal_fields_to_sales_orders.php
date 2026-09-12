<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Odoo `sale.order.access_token` denkliği: müşteriye e-postayla giden benzersiz
 * bir token içeren public link. Kimlik doğrulaması olmadan teklifi görüntüleme
 * ve onay/red işlemi yapılmasını sağlar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table): void {
            $table->string('access_token', 64)->nullable()->unique()->after('validity_date');
            $table->timestamp('customer_confirmed_at')->nullable()->after('access_token');
            $table->timestamp('customer_declined_at')->nullable()->after('customer_confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table): void {
            $table->dropColumn(['access_token', 'customer_confirmed_at', 'customer_declined_at']);
        });
    }
};
