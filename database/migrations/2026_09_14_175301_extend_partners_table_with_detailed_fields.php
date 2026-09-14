<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cari kartını tam ERP standardına genişletir (4 grup):
 *  1) Temel: partner_code (tenant içinde benzersiz), entity_type, group_code
 *  2) Yasal: tax_office, national_id (TCKN), e_invoice_status, e_invoice_alias
 *  3) İletişim/Adres: country, city, district, fax, contact_person
 *  4) Finans/Muhasebe: account_code_receivable (120), account_code_payable (320),
 *     currency_code, credit_limit, notes, is_active
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->string('partner_code', 32)->nullable()->after('tenant_id');
            $table->enum('entity_type', ['company', 'individual'])->default('company')->after('name');
            $table->string('group_code', 64)->nullable()->after('entity_type');

            $table->string('tax_office', 128)->nullable()->after('tax_number');
            $table->string('national_id', 32)->nullable()->after('tax_office');
            $table->enum('e_invoice_status', ['none', 'e_arsiv', 'e_fatura'])->default('none')->after('national_id');
            $table->string('e_invoice_alias', 128)->nullable()->after('e_invoice_status');

            $table->string('country', 64)->default('Türkiye')->after('address');
            $table->string('city', 64)->nullable()->after('country');
            $table->string('district', 64)->nullable()->after('city');
            $table->string('fax', 32)->nullable()->after('phone');
            $table->string('contact_person', 128)->nullable()->after('fax');

            $table->string('account_code_receivable', 32)->nullable()->after('payment_term_days');
            $table->string('account_code_payable', 32)->nullable()->after('account_code_receivable');
            $table->string('currency_code', 8)->default('TRY')->after('account_code_payable');
            $table->decimal('credit_limit', 15, 2)->default(0)->after('currency_code');

            $table->text('notes')->nullable()->after('credit_limit');
            $table->boolean('is_active')->default(true)->after('notes');

            $table->unique(['tenant_id', 'partner_code'], 'partners_tenant_partner_code_unique');
        });
    }

    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->dropUnique('partners_tenant_partner_code_unique');
            $table->dropColumn([
                'partner_code', 'entity_type', 'group_code',
                'tax_office', 'national_id', 'e_invoice_status', 'e_invoice_alias',
                'country', 'city', 'district', 'fax', 'contact_person',
                'account_code_receivable', 'account_code_payable', 'currency_code', 'credit_limit',
                'notes', 'is_active',
            ]);
        });
    }
};
