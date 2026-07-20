<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_adjustments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('location_id');
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->enum('status', ['draft', 'counting', 'pending_approval', 'approved', 'cancelled']);
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        // PRD boşluk doldurma: sayım satırları (additive birleştirme burada)
        Schema::create('inventory_adjustment_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('inventory_adjustment_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('lot_id')->nullable();
            $table->decimal('counted_qty', 15, 4)->default(0);      // referans birimde, additive
            $table->decimal('theoretical_qty', 15, 4)->nullable();  // onay anında snapshot
            $table->timestamps();

            $table->index(['tenant_id', 'inventory_adjustment_id'], 'ia_lines_tenant_adjustment_idx');
        });

        Schema::create('warehouse_transfers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('from_location_id');
            $table->unsignedBigInteger('to_location_id');
            $table->unsignedBigInteger('created_by');
            $table->enum('status', ['draft', 'in_transit', 'completed', 'cancelled']);
            $table->timestamps();
        });

        // PRD boşluk doldurma: transfer satırları
        Schema::create('warehouse_transfer_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('warehouse_transfer_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('uom_id');
            $table->unsignedBigInteger('lot_id')->nullable();
            $table->decimal('qty', 15, 4); // pozitif, referans birimde saklanır
            $table->timestamps();

            $table->index(['tenant_id', 'warehouse_transfer_id'], 'wt_lines_tenant_transfer_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_transfer_lines');
        Schema::dropIfExists('warehouse_transfers');
        Schema::dropIfExists('inventory_adjustment_lines');
        Schema::dropIfExists('inventory_adjustments');
    }
};
