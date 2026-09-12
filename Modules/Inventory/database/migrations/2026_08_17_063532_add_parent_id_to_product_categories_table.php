<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Odoo `product.category` hiyerarşisi: kategoriler ağaç yapısında (parent_id
 * kendi tablosuna FK). Bu sayede "Alkolsüz İçecekler → Su → Doğal Kaynak Suyu"
 * gibi ürün sınıflandırması yapılabilir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $table): void {
            $table->foreignId('parent_id')->nullable()->after('tenant_id')
                ->constrained('product_categories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('product_categories', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('parent_id');
        });
    }
};
