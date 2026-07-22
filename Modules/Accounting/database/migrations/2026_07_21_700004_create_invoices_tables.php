<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('partner_id');
            $table->enum('type', ['purchase', 'sale']);
            $table->morphs('source'); // purchase_order | sales_order
            $table->enum('status', ['draft', 'posted', 'paid', 'cancelled']);
            $table->timestamps();

            $table->index(['tenant_id', 'partner_id']);
        });

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('invoice_id');
            $table->unsignedBigInteger('product_id');
            $table->decimal('qty', 15, 4);
            $table->decimal('unit_price', 15, 4);
            $table->unsignedBigInteger('tax_rate_id')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
    }
};
