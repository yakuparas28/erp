<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');
            $table->decimal('base_price', 15, 4)->default(0);
            $table->timestamps();
        });

        Schema::create('product_attributes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');
            $table->enum('creation_mode', ['instant', 'dynamic', 'never'])->default('instant');
            $table->timestamps();
        });

        Schema::create('product_attribute_values', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('product_attribute_id');
            $table->string('value');
            $table->decimal('price_extra', 15, 4)->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'product_attribute_id'], 'pav_tenant_attribute_idx');
        });

        Schema::create('product_template_attribute_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('product_template_id');
            $table->unsignedBigInteger('product_attribute_id');
            $table->timestamps();

            $table->unique(['product_template_id', 'product_attribute_id'], 'ptal_template_attribute_unique');
        });

        Schema::create('product_variant_attribute_values', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('product_id'); // varyant
            $table->unsignedBigInteger('product_attribute_value_id');
            $table->timestamps();

            $table->unique(['product_id', 'product_attribute_value_id'], 'pvav_product_value_unique');
        });

        Schema::create('product_kit_components', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('kit_product_id');
            $table->unsignedBigInteger('component_product_id');
            $table->decimal('qty', 15, 4);
            $table->timestamps();

            $table->index(['tenant_id', 'kit_product_id'], 'pkc_tenant_kit_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_kit_components');
        Schema::dropIfExists('product_variant_attribute_values');
        Schema::dropIfExists('product_template_attribute_lines');
        Schema::dropIfExists('product_attribute_values');
        Schema::dropIfExists('product_attributes');
        Schema::dropIfExists('product_templates');
    }
};
