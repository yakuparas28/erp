<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Odoo `product.template` denkliği için eksik alanlar:
 *   - barcode: birincil barkod (tenant içinde benzersiz). product_barcodes
 *     tablosu ek/alternatif barkodlar için hala kullanılabilir.
 *   - list_price: satış fiyatı
 *   - sale_ok / purchase_ok: satılabilir / satın alınabilir flag'leri
 *   - description_sale: müşteriye görünen açıklama
 *   - description: iç not
 *   - hs_code: gümrük tarife istatistik pozisyon (Odoo intrastat)
 *   - country_of_origin: menşei ülke ISO kodu (Odoo l10n_intrastat)
 *   - image_path: ürün görseli
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('barcode')->nullable()->after('sku');
            $table->decimal('list_price', 15, 4)->default('0')->after('avco_unit_cost');
            $table->boolean('sale_ok')->default(true)->after('list_price');
            $table->boolean('purchase_ok')->default(true)->after('sale_ok');
            $table->text('description')->nullable()->after('purchase_ok');
            $table->text('description_sale')->nullable()->after('description');
            $table->string('hs_code', 20)->nullable()->after('description_sale');
            $table->string('country_of_origin', 2)->nullable()->after('hs_code');
            $table->string('image_path')->nullable()->after('country_of_origin');

            $table->unique(['tenant_id', 'barcode']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropUnique(['tenant_id', 'barcode']);
            $table->dropColumn(['barcode', 'list_price', 'sale_ok', 'purchase_ok', 'description', 'description_sale', 'hs_code', 'country_of_origin', 'image_path']);
        });
    }
};
