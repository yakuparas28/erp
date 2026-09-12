<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Odoo `stock.picking.batch` denkliği: birden fazla `warehouse_transfer`'ı bir
 * pakette gruplayıp aynı anda tamamlama. `warehouse_transfers.batch_id` batch'e
 * bağ. Batch complete edildiğinde toplanan transferler sırayla complete edilir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_batches', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');
            $table->enum('status', ['draft', 'in_progress', 'done', 'cancelled'])->default('draft');
            $table->foreignId('done_by_user_id')->nullable()->constrained('users');
            $table->timestamps();

            $table->index('tenant_id');
        });

        Schema::table('warehouse_transfers', function (Blueprint $table): void {
            $table->foreignId('batch_id')->nullable()->after('tenant_id')
                ->constrained('transfer_batches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_transfers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('batch_id');
        });

        Schema::dropIfExists('transfer_batches');
    }
};
