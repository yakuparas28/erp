<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('partner_id'); // müşteri
            $table->unsignedBigInteger('location_id'); // rezerve/teslim lokasyonu
            $table->unsignedBigInteger('created_by');
            $table->enum('status', ['draft', 'quotation_sent', 'confirmed', 'done', 'cancelled']);
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('sales_order_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('sales_order_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('uom_id');
            $table->decimal('qty', 15, 4);
            $table->decimal('unit_price', 15, 4);
            $table->decimal('delivered_qty', 15, 4)->default(0);
            $table->unsignedBigInteger('tax_rate_id')->nullable(); // Faz 9: tax_rates henüz yok
            $table->timestamps();

            $table->index(['tenant_id', 'sales_order_id'], 'sol_tenant_so_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_order_lines');
        Schema::dropIfExists('sales_orders');
    }
};
