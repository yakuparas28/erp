<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fatura tutar bazlı onay eşiği. Bu değer üzerinde subtotal'a sahip
 * satın alma faturaları posting öncesi onay akışına girer. NULL = kapalı.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenants') || Schema::hasColumn('tenants', 'invoice_approval_threshold')) {
            return;
        }

        Schema::table('tenants', function (Blueprint $table) {
            $table->decimal('invoice_approval_threshold', 15, 4)->nullable()->after('address');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tenants') || ! Schema::hasColumn('tenants', 'invoice_approval_threshold')) {
            return;
        }

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('invoice_approval_threshold');
        });
    }
};
