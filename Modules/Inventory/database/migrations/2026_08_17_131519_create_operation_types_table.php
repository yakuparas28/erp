<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Odoo `stock.picking.type` denkliği: her depoda operasyon tipi
 * (Receipt/Delivery/Internal/Scrap) için sıra prefix'i + varsayılan kaynak/hedef.
 * Şu an referans veri; sonraki fazda transfer numaralandırması bu prefix'ten alır.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operation_types', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->enum('type', ['incoming', 'outgoing', 'internal', 'scrap']);
            $table->string('sequence_prefix')->nullable();
            $table->foreignId('default_source_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('default_destination_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'warehouse_id', 'code']);
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operation_types');
    }
};
