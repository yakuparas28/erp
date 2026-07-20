<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Landed cost'ta by_weight/by_volume dağıtımı için (PRD boşluk doldurma).
            $table->decimal('weight', 15, 4)->nullable()->after('avco_unit_cost');
            $table->decimal('volume', 15, 4)->nullable()->after('weight');
        });

        Schema::create('stock_valuation_layers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('stock_move_id');
            $table->decimal('qty', 15, 4);             // giriş +, çıkış (audit) -
            $table->decimal('unit_cost', 15, 4);
            $table->decimal('remaining_value', 15, 4);  // FIFO'da tüketildikçe azalır
            $table->timestamps();

            $table->index(['tenant_id', 'product_id', 'created_at'], 'svl_tenant_product_created_idx');
        });

        Schema::create('landed_costs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->enum('split_method', ['by_weight', 'by_volume', 'by_quantity', 'by_current_cost']);
            $table->enum('status', ['draft', 'validated', 'cancelled']);
            $table->timestamps();
        });

        Schema::create('landed_cost_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('landed_cost_id');
            $table->string('description');
            $table->decimal('amount', 15, 4);
            $table->timestamps();

            $table->index(['tenant_id', 'landed_cost_id'], 'lcl_tenant_landed_cost_idx');
        });

        Schema::create('landed_cost_distributions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('landed_cost_id');
            $table->unsignedBigInteger('stock_move_id');
            $table->decimal('allocated_amount', 15, 4);
            $table->timestamps();

            $table->index(['tenant_id', 'landed_cost_id'], 'lcd_tenant_landed_cost_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landed_cost_distributions');
        Schema::dropIfExists('landed_cost_lines');
        Schema::dropIfExists('landed_costs');
        Schema::dropIfExists('stock_valuation_layers');

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['weight', 'volume']);
        });
    }
};
