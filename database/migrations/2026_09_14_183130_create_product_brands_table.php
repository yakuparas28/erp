<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marka yönetimi (Envanter). Tenant-scoped, isim tenant içinde
 * benzersiz. Product tarafında opsiyonel product_brand_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_brands', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name', 128);
            $table->string('code', 32)->nullable();
            $table->string('website', 255)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'name'], 'brands_tenant_name_unique');
            $table->index(['tenant_id', 'is_active']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('product_brand_id')->nullable()->after('product_category_id');
            $table->index('product_brand_id');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['product_brand_id']);
            $table->dropColumn('product_brand_id');
        });
        Schema::dropIfExists('product_brands');
    }
};
