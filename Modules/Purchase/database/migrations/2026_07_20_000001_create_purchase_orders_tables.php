<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('partner_id'); // tedarikçi
            $table->unsignedBigInteger('created_by');
            $table->enum('bill_control_policy', ['ordered_qty', 'received_qty'])->default('received_qty');
            $table->enum('status', ['draft', 'rfq_sent', 'confirmed', 'done', 'cancelled']);
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('purchase_order_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('purchase_order_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('uom_id');
            $table->decimal('qty', 15, 4);
            $table->decimal('unit_price', 15, 4);
            $table->unsignedBigInteger('tax_rate_id')->nullable(); // Faz 9: tax_rates henüz yok
            $table->timestamps();

            $table->index(['tenant_id', 'purchase_order_id'], 'pol_tenant_po_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_lines');
        Schema::dropIfExists('purchase_orders');
    }
};
