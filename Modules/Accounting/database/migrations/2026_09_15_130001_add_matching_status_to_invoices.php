<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 3-way matching (PO amount ↔ mal kabul miktarları × birim fiyat ↔ fatura tutarı).
 * Değerler:
 * - not_applicable: kaynak bir purchase_order değil (satış faturaları, manuel vs.)
 * - pending: eşleştirme yapılmadı / eksik veri
 * - matched: PO + GR + Invoice tutarları eşleşti (kuruş toleransıyla)
 * - mismatch: en az bir taraf tutmuyor — kullanıcı uyarısı
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('invoices')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'matching_status')) {
                $table->string('matching_status', 20)->default('pending')->after('status');
                $table->index(['tenant_id', 'matching_status'], 'invoices_matching_status_idx');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('invoices') || ! Schema::hasColumn('invoices', 'matching_status')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoices_matching_status_idx');
            $table->dropColumn('matching_status');
        });
    }
};
