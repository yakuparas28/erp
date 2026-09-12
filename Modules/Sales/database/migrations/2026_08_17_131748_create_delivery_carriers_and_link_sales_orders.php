<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Odoo `delivery.carrier` denkliği: kargo firmaları + izleme URL şablonu.
 * SalesOrder'a `delivery_carrier_id` + `tracking_number` opsiyonel FK/alanları.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_carriers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('tracking_url_template')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
            $table->index('tenant_id');
        });

        Schema::table('sales_orders', function (Blueprint $table): void {
            $table->foreignId('delivery_carrier_id')->nullable()->after('location_id')
                ->constrained('delivery_carriers')->nullOnDelete();
            $table->string('tracking_number')->nullable()->after('delivery_carrier_id');
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('delivery_carrier_id');
            $table->dropColumn('tracking_number');
        });

        Schema::dropIfExists('delivery_carriers');
    }
};
