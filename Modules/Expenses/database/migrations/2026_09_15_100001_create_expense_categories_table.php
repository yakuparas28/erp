<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Masraf kategorileri (Odoo Expense Categories denk).
 * unit_price = 0 ise "actual cost" — kullanıcı fatura tutarını girer.
 * unit_price > 0 ise "flat rate" — miktar * unit_price ile hesaplanır (yemek, km, günlük harcırah).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('code', 32)->nullable();
            $table->string('name', 128);
            $table->text('description')->nullable();
            $table->decimal('unit_price', 15, 4)->default(0);
            $table->string('unit_label', 32)->default('Adet');
            $table->string('expense_account_code', 32)->nullable();
            $table->boolean('is_reinvoiceable')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'code'], 'expense_categories_tenant_code_unique');
            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_categories');
    }
};
