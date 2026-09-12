<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Odoo `sale.order.route_id` denkliği: SO confirm sırasında bu rota
 * seçilirse RouteService::executePull() tetiklenir (pull kuralları).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table): void {
            $table->foreignId('route_id')->nullable()->after('location_id')
                ->constrained('routes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('route_id');
        });
    }
};
