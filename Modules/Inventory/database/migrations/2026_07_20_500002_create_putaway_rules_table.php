<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('putaway_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('product_id')->nullable();          // null: kategori bazlı
            $table->unsignedBigInteger('product_category_id')->nullable(); // PRD 4.1.4 düzeltmesi — baştan var
            $table->unsignedBigInteger('source_location_id');
            $table->unsignedBigInteger('dest_location_id');
            $table->unsignedInteger('sequence');
            $table->timestamps();

            $table->index(['tenant_id', 'source_location_id', 'sequence'], 'putaway_tenant_source_seq_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('putaway_rules');
    }
};
