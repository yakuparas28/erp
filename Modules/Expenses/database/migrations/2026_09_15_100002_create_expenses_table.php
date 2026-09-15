<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Personel masraf kaydı. Onay akışı generic app/Services/Approval üstünden;
 * subject_type='expense'. Post edildikten sonra Accounting'e journal entry
 * olarak yansır (accounting_journal_entry_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('expense_category_id');
            $table->string('description', 255);
            $table->date('expense_date');
            $table->decimal('qty', 12, 4)->default(1);
            $table->decimal('unit_price', 15, 4);
            $table->decimal('total_amount', 15, 4);
            $table->string('currency_code', 8)->default('TRY');
            $table->enum('paid_by', ['employee', 'company'])->default('employee');
            $table->enum('status', ['draft', 'submitted', 'approved', 'refused', 'posted', 'paid'])->default('draft');
            $table->string('receipt_path', 512)->nullable();
            $table->text('notes')->nullable();
            $table->string('reference', 64)->nullable();
            $table->unsignedBigInteger('sales_order_id')->nullable();
            $table->unsignedBigInteger('posted_journal_entry_id')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->text('refuse_reason')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'employee_id']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'expense_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
