<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Odoo `product.attribute` + `product.attribute.value` denkliği:
 *   - display_type: UI'da nasıl görüneceği (select / radio / pill / color)
 *   - html_color: color mode için hex kod
 *   - image_path: value bazlı görsel (swatch)
 *   - sequence: manuel sıralama (drag-drop)
 *   - active: soft archive
 *   - is_custom: end-user'ın bu değer için serbest metin girmesine izin verilsin mi
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_attributes', function (Blueprint $table): void {
            $table->enum('display_type', ['select', 'radio', 'pill', 'color'])->default('select')->after('creation_mode');
            $table->unsignedInteger('sequence')->default(0)->after('display_type');
            $table->boolean('active')->default(true)->after('sequence');
        });

        Schema::table('product_attribute_values', function (Blueprint $table): void {
            $table->string('html_color', 7)->nullable()->after('price_extra');
            $table->string('image_path')->nullable()->after('html_color');
            $table->unsignedInteger('sequence')->default(0)->after('image_path');
            $table->boolean('active')->default(true)->after('sequence');
            $table->boolean('is_custom')->default(false)->after('active');
        });
    }

    public function down(): void
    {
        Schema::table('product_attributes', function (Blueprint $table): void {
            $table->dropColumn(['display_type', 'sequence', 'active']);
        });

        Schema::table('product_attribute_values', function (Blueprint $table): void {
            $table->dropColumn(['html_color', 'image_path', 'sequence', 'active', 'is_custom']);
        });
    }
};
