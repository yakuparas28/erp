<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bordro için Employee alanları. gross_salary NULL ise personel
 * bordroya girmez (henüz atanmamış). expense_type: 720 direkt işçilik
 * (üretim/operasyon) veya 770 genel yönetim gideri (idari personel).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employees')) {
            return;
        }

        Schema::table('employees', function (Blueprint $table) {
            if (! Schema::hasColumn('employees', 'gross_salary')) {
                $table->decimal('gross_salary', 15, 4)->nullable()->after('title');
            }
            if (! Schema::hasColumn('employees', 'iban')) {
                $table->string('iban', 34)->nullable()->after('gross_salary');
            }
            if (! Schema::hasColumn('employees', 'salary_expense_type')) {
                $table->enum('salary_expense_type', ['direct_labor', 'admin'])->default('admin')->after('iban');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('employees')) {
            return;
        }

        Schema::table('employees', function (Blueprint $table) {
            foreach (['gross_salary', 'iban', 'salary_expense_type'] as $col) {
                if (Schema::hasColumn('employees', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
