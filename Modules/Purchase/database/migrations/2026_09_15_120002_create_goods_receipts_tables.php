<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mal Kabul Fişi (Goods Receipt) — PO'nun her receive() çağrısında bir
 * GoodsReceipt yaratılır. 3-way matching (Faz 3) için data source olur.
 * Numaralandırma tenant içinde: MKF-2026-000001.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('purchase_order_id');
            $table->string('receipt_no', 32);
            $table->date('receipt_date');
            $table->unsignedBigInteger('warehouse_location_id')->nullable();
            $table->string('waybill_no', 64)->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['draft', 'received', 'cancelled'])->default('received');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'receipt_no'], 'goods_receipts_tenant_no_unique');
            $table->index(['tenant_id', 'purchase_order_id']);
            $table->index(['tenant_id', 'receipt_date']);
        });

        Schema::create('goods_receipt_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('goods_receipt_id');
            $table->unsignedBigInteger('purchase_order_line_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('uom_id');
            $table->decimal('qty', 12, 4);
            $table->timestamps();

            $table->index(['tenant_id', 'goods_receipt_id'], 'grl_tenant_receipt_idx');
            $table->index(['tenant_id', 'purchase_order_line_id'], 'grl_tenant_line_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_lines');
        Schema::dropIfExists('goods_receipts');
    }
};
