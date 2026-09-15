<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Teklif/Satış Siparişi/Satın Alma Siparişi için tutar bazlı onay eşikleri.
 * Faz 4'te fatura için eklediğimiz kolonun kardeşleri. NULL = akış kapalı.
 * Tenant Admin muhasebe ayarları ekranından düzenler.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenants')) {
            return;
        }

        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'quotation_approval_threshold')) {
                $table->decimal('quotation_approval_threshold', 15, 4)->nullable()->after('invoice_approval_threshold');
            }
            if (! Schema::hasColumn('tenants', 'sales_order_approval_threshold')) {
                $table->decimal('sales_order_approval_threshold', 15, 4)->nullable()->after('quotation_approval_threshold');
            }
            if (! Schema::hasColumn('tenants', 'purchase_order_approval_threshold')) {
                $table->decimal('purchase_order_approval_threshold', 15, 4)->nullable()->after('sales_order_approval_threshold');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tenants')) {
            return;
        }

        Schema::table('tenants', function (Blueprint $table) {
            foreach (['quotation_approval_threshold', 'sales_order_approval_threshold', 'purchase_order_approval_threshold'] as $col) {
                if (Schema::hasColumn('tenants', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
