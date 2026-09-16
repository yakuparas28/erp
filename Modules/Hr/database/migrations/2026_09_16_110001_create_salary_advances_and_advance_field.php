<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Personel maaş avansı. Verildiğinde dr 196 (Personel Avansları) /
 * cr banka. Ay sonu bordro ödemesinde payslip.advance_deducted kadarı
 * net maaştan düşülür (dr 335 → cr 196 + cr banka).
 *
 * status:
 *   outstanding  → avans verildi, henüz mahsup edilmedi
 *   deducted     → bir payslip ödemesinde mahsup edildi (kısmi/tam)
 *   cancelled    → iptal edildi (ödendiyse ters JE gerekir)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_advances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('employee_id');
            $table->decimal('amount', 15, 4);
            $table->date('granted_at');
            $table->unsignedBigInteger('paid_from_journal_id');
            $table->enum('status', ['outstanding', 'deducted', 'cancelled'])->default('outstanding');
            $table->unsignedBigInteger('deducted_in_payslip_id')->nullable();
            $table->date('deducted_at')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'employee_id', 'status'], 'sa_tenant_emp_status_idx');
            $table->index(['tenant_id', 'status'], 'sa_tenant_status_idx');
        });

        if (Schema::hasTable('payslips') && ! Schema::hasColumn('payslips', 'advance_deducted')) {
            Schema::table('payslips', function (Blueprint $table) {
                $table->decimal('advance_deducted', 15, 4)->default(0)->after('net_salary');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payslips') && Schema::hasColumn('payslips', 'advance_deducted')) {
            Schema::table('payslips', function (Blueprint $table) {
                $table->dropColumn('advance_deducted');
            });
        }

        Schema::dropIfExists('salary_advances');
    }
};
