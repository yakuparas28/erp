<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');
            // Muhasebe varsayılanları — Faz 9'da kullanılacak (PRD 4.1.4)
            $table->unsignedBigInteger('stock_input_account_id')->nullable();
            $table->unsignedBigInteger('stock_output_account_id')->nullable();
            $table->unsignedBigInteger('expense_account_id')->nullable();
            $table->unsignedBigInteger('income_account_id')->nullable();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('product_category_id')->nullable();
            $table->unsignedBigInteger('uom_id'); // referans birim
            $table->unsignedBigInteger('product_template_id')->nullable(); // Faz 3: varyant
            $table->string('name');
            $table->string('sku')->nullable();
            $table->enum('track_by', ['none', 'lot', 'serial'])->default('none');
            $table->enum('product_type', ['stockable', 'consumable', 'service'])->default('stockable');
            $table->boolean('is_kit')->default(false); // Faz 3
            $table->enum('cost_method', ['fifo', 'avco', 'standard'])->default('fifo'); // Faz 5
            $table->decimal('standard_cost', 15, 4)->nullable();
            $table->decimal('avco_unit_cost', 15, 4)->nullable();
            $table->decimal('current_stock', 15, 4)->default(0); // quant toplamından türeyen özet
            $table->timestamps();

            $table->unique(['tenant_id', 'sku']);
            $table->index(['tenant_id', 'product_category_id']);
        });

        Schema::create('product_barcodes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('uom_id')->nullable(); // null: referans birim
            $table->string('barcode');
            $table->timestamps();

            $table->unique(['tenant_id', 'barcode']);
        });

        Schema::create('product_lots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('product_id');
            $table->string('lot_number');
            $table->date('expiry_date')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'product_id', 'lot_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_lots');
        Schema::dropIfExists('product_barcodes');
        Schema::dropIfExists('products');
        Schema::dropIfExists('product_categories');
    }
};
