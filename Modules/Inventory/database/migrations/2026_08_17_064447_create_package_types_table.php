<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Odoo `stock.package.type` denkliği: sevkiyat kap tipi (kutu/palet/konteyner)
 * boyutları ve max ağırlık. Barkod tenant içinde benzersiz, isteğe bağlı.
 * Şu an referans veri; sonraki fazlarda paket ile teslim entegrasyonu eklenecek.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_types', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');
            $table->string('barcode')->nullable();
            $table->decimal('height', 12, 4)->default('0');
            $table->decimal('width', 12, 4)->default('0');
            $table->decimal('packaging_length', 12, 4)->default('0');
            $table->decimal('max_weight', 12, 4)->default('0');
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
            $table->unique(['tenant_id', 'barcode']);
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_types');
    }
};
