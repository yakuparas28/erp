<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sevk irsaliyesi (Delivery Note) — SO'nun her deliver() çağrısında bir
 * DeliveryNote yaratılır. TR e-İrsaliye ile ileride entegre edilebilir.
 * Numaralandırma tenant içinde: IRS-2026-000001.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('sales_order_id');
            $table->string('note_no', 32);
            $table->date('delivery_date');
            $table->string('driver_name', 128)->nullable();
            $table->string('vehicle_plate', 32)->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['draft', 'delivered', 'cancelled'])->default('delivered');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'note_no'], 'delivery_notes_tenant_no_unique');
            $table->index(['tenant_id', 'sales_order_id']);
            $table->index(['tenant_id', 'delivery_date']);
        });

        Schema::create('delivery_note_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('delivery_note_id');
            $table->unsignedBigInteger('sales_order_line_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('uom_id');
            $table->decimal('qty', 12, 4);
            $table->timestamps();

            $table->index(['tenant_id', 'delivery_note_id'], 'dnl_tenant_note_idx');
            $table->index(['tenant_id', 'sales_order_line_id'], 'dnl_tenant_line_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_note_lines');
        Schema::dropIfExists('delivery_notes');
    }
};
