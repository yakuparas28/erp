<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Odoo `stock.storage.category` denkliği: lokasyonlara kapasite kısıtı
 * (max ağırlık) ve doldurma politikası (empty/same/mixed) tanımlar.
 * Lokasyon başına opsiyonel FK; şu an sadece referans veri, sonraki fazda
 * putaway ve stock move'lar kapasite kontrolü yapacak.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storage_categories', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');
            $table->decimal('max_weight', 12, 4)->default('0');
            $table->enum('allow_new_product', ['empty', 'same', 'mixed'])->default('mixed');
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
            $table->index('tenant_id');
        });

        Schema::table('locations', function (Blueprint $table): void {
            $table->foreignId('storage_category_id')->nullable()->after('parent_id')
                ->constrained('storage_categories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('storage_category_id');
        });

        Schema::dropIfExists('storage_categories');
    }
};
