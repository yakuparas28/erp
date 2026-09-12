<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Odoo `stock.warehouse.reception_steps` / `delivery_steps` denkliği:
 *   - one_step: Receipt → Stok (direkt)
 *   - two_step: Receipt → Alım Bekliyor → Stok / veya Stok → Sevk Bekliyor → Müşteri
 *   - three_step: Receipt → Alım Bekliyor → QC → Stok / Stok → Pack → Sevk → Müşteri
 *
 * Bu migration yalnızca yapılandırma alanını ekler; PO/SO servisleri bu
 * alanları kullanarak ara transferi otomatik oluşturur (Faz D2 controller).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouses', function (Blueprint $table): void {
            $table->enum('reception_steps', ['one_step', 'two_step', 'three_step'])->default('one_step')->after('name');
            $table->enum('delivery_steps', ['one_step', 'two_step', 'three_step'])->default('one_step')->after('reception_steps');
            $table->foreignId('input_location_id')->nullable()->after('delivery_steps')->constrained('locations')->nullOnDelete();
            $table->foreignId('quality_location_id')->nullable()->after('input_location_id')->constrained('locations')->nullOnDelete();
            $table->foreignId('output_location_id')->nullable()->after('quality_location_id')->constrained('locations')->nullOnDelete();
            $table->foreignId('pack_location_id')->nullable()->after('output_location_id')->constrained('locations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('input_location_id');
            $table->dropConstrainedForeignId('quality_location_id');
            $table->dropConstrainedForeignId('output_location_id');
            $table->dropConstrainedForeignId('pack_location_id');
            $table->dropColumn(['reception_steps', 'delivery_steps']);
        });
    }
};
