<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_quants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('location_id');
            $table->unsignedBigInteger('lot_id')->nullable();
            $table->decimal('qty', 15, 4)->default(0);          // referans birimde
            $table->decimal('reserved_qty', 15, 4)->default(0); // Faz 8: satış rezervasyonu
            $table->timestamps();

            // NOT: lot_id NULL iken unique NULL'ları ayrı sayar (MySQL/SQLite);
            // tekillik servis katmanında lockForUpdate+firstOrCreate ile garanti edilir.
            $table->unique(['tenant_id', 'product_id', 'location_id', 'lot_id']);
        });

        Schema::create('stock_moves', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('from_location_id')->nullable(); // null: dış kaynak
            $table->unsignedBigInteger('to_location_id')->nullable();   // null: dış çıkış
            $table->unsignedBigInteger('uom_id');
            $table->unsignedBigInteger('lot_id')->nullable();
            $table->decimal('qty', 15, 4); // İŞARETLİ: giriş +, çıkış −
            $table->morphs('reference');   // inventory_adjustment | warehouse_transfer | ...
            $table->timestamps();

            $table->index(['tenant_id', 'product_id', 'created_at']);
            $table->index(['tenant_id', 'from_location_id']);
            $table->index(['tenant_id', 'to_location_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_moves');
        Schema::dropIfExists('stock_quants');
    }
};
