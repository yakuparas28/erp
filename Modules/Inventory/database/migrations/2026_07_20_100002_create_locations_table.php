<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('warehouse_id')->nullable(); // sanal lokasyonlarda null
            $table->unsignedBigInteger('parent_id')->nullable();    // self-referencing ağaç
            $table->string('name');
            $table->enum('type', ['internal', 'view', 'customer', 'supplier', 'inventory_loss', 'transit']);
            $table->boolean('counting_lock')->default(false);
            $table->enum('removal_strategy', ['fifo', 'fefo', 'lifo', 'closest'])->default('fifo');
            $table->timestamps();

            $table->index(['tenant_id', 'warehouse_id']);
            $table->index(['tenant_id', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
