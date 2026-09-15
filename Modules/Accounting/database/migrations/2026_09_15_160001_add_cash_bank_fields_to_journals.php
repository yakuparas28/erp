<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kasa/Banka journal'larını "hesap" olarak zenginleştirir. Bir tenantta
 * çok sayıda kasa (merkez, şube, kasiyer bazlı) ve banka hesabı (farklı
 * bankalar/dövizler) tanımlanabilsin diye. Chart_of_account_id
 * girildiğinde PaymentService bu hesabı JE'de kullanır — girilmezse
 * eski davranış (defaults '100'/'102') korunur.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('journals')) {
            return;
        }

        Schema::table('journals', function (Blueprint $table) {
            if (! Schema::hasColumn('journals', 'code')) {
                $table->string('code', 32)->nullable()->after('name');
            }
            if (! Schema::hasColumn('journals', 'bank_name')) {
                $table->string('bank_name', 128)->nullable()->after('code');
            }
            if (! Schema::hasColumn('journals', 'iban')) {
                $table->string('iban', 34)->nullable()->after('bank_name');
            }
            if (! Schema::hasColumn('journals', 'account_no')) {
                $table->string('account_no', 64)->nullable()->after('iban');
            }
            if (! Schema::hasColumn('journals', 'currency_id')) {
                $table->unsignedBigInteger('currency_id')->nullable()->after('account_no');
            }
            if (! Schema::hasColumn('journals', 'chart_of_account_id')) {
                $table->unsignedBigInteger('chart_of_account_id')->nullable()->after('currency_id');
            }
            if (! Schema::hasColumn('journals', 'opening_balance')) {
                $table->decimal('opening_balance', 15, 4)->default(0)->after('chart_of_account_id');
            }
            if (! Schema::hasColumn('journals', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('opening_balance');
            }
        });

        // code tenant içinde unique — indeks ayrıca eklenir çünkü nullable
        Schema::table('journals', function (Blueprint $table) {
            $table->index(['tenant_id', 'type', 'is_active'], 'journals_tenant_type_active_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('journals')) {
            return;
        }

        Schema::table('journals', function (Blueprint $table) {
            $table->dropIndex('journals_tenant_type_active_idx');
            foreach (['code', 'bank_name', 'iban', 'account_no', 'currency_id', 'chart_of_account_id', 'opening_balance', 'is_active'] as $col) {
                if (Schema::hasColumn('journals', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
