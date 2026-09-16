<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Basit bordro (Seviye 1). payroll_periods = ay bazlı bordro dönemi;
 * payslips = her personel için hesaplanan brüt/kesinti/net satır.
 * Post edildiğinde tek toplu JE üretilir (720/770 → 335 + 360 + 361).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->smallInteger('year');
            $table->tinyInteger('month');
            $table->enum('status', ['draft', 'calculated', 'posted', 'cancelled'])->default('draft');
            $table->decimal('total_gross', 15, 4)->default(0);
            $table->decimal('total_deductions', 15, 4)->default(0);
            $table->decimal('total_net', 15, 4)->default(0);
            $table->decimal('total_employer_cost', 15, 4)->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->date('posted_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'year', 'month'], 'payroll_periods_period_uk');
            $table->index(['tenant_id', 'status'], 'payroll_periods_tenant_status_idx');
        });

        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('payroll_period_id');
            $table->unsignedBigInteger('employee_id');
            $table->decimal('gross_salary', 15, 4);
            $table->decimal('sgk_worker', 15, 4)->default(0);
            $table->decimal('unemployment_worker', 15, 4)->default(0);
            $table->decimal('income_tax', 15, 4)->default(0);
            $table->decimal('stamp_tax', 15, 4)->default(0);
            $table->decimal('total_deductions', 15, 4)->default(0);
            $table->decimal('net_salary', 15, 4);
            $table->decimal('sgk_employer', 15, 4)->default(0);
            $table->decimal('unemployment_employer', 15, 4)->default(0);
            $table->decimal('total_employer_cost', 15, 4)->default(0);
            $table->enum('status', ['calculated', 'paid'])->default('calculated');
            $table->date('paid_at')->nullable();
            $table->unsignedBigInteger('paid_from_journal_id')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'payroll_period_id', 'employee_id'], 'payslips_period_emp_uk');
            $table->index(['tenant_id', 'status'], 'payslips_tenant_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslips');
        Schema::dropIfExists('payroll_periods');
    }
};
