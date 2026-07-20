<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // "Varsayılan tedarikçi" PRD 3.8'de örtük — replenishment önerisinin
            // hangi tedarikçiye draft PO açacağını belirlemek için gerekli.
            $table->unsignedBigInteger('default_supplier_id')->nullable()->after('product_category_id');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('default_supplier_id');
        });
    }
};
