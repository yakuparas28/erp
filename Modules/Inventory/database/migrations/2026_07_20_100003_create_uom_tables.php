<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('uom_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('uoms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('uom_category_id');
            $table->string('name');
            $table->decimal('factor', 15, 6); // kategori referans birimine çevrim katsayısı
            $table->boolean('is_reference')->default(false);
            $table->timestamps();

            $table->index(['tenant_id', 'uom_category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uoms');
        Schema::dropIfExists('uom_categories');
    }
};
