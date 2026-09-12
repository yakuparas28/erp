<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Odoo `product.template.attribute.exclusion` benzeri: belli bir attribute
 * değerinin, belli başka bir attribute değeriyle aynı varyantta bulunmasını
 * yasaklar. Örn: "Kırmızı" + "XL beden" imkansızsa, cartesian expansion sırasında
 * bu kombinasyon atlanır. tenant içinde simetrik değil — user "A B'yi dışlar"
 * ekler; expansion iki yönde de kontrol eder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_attribute_exclusions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('product_attribute_value_id')->constrained('product_attribute_values')->cascadeOnDelete();
            $table->foreignId('excluded_value_id')->constrained('product_attribute_values')->cascadeOnDelete();
            $table->foreignId('product_template_id')->nullable()->constrained('product_templates')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['product_attribute_value_id', 'excluded_value_id', 'product_template_id'], 'pae_unique');
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_attribute_exclusions');
    }
};
