<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reordering_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('location_id');
            $table->decimal('min_qty', 15, 4);
            $table->decimal('max_qty', 15, 4);
            $table->enum('trigger_type', ['auto', 'manual']);
            $table->timestamps();

            $table->unique(['tenant_id', 'product_id', 'location_id'], 'reordering_rules_tenant_product_location_unique');
        });

        Schema::create('replenishment_suggestions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('reordering_rule_id');
            $table->decimal('suggested_qty', 15, 4);
            $table->enum('status', ['pending', 'acknowledged', 'dismissed']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('replenishment_suggestions');
        Schema::dropIfExists('reordering_rules');
    }
};
