<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit sonucu tespit edilen 4 index eksikliği. Hepsi büyüyen tablolar
 * üzerinde hot query path'lerini destekler:
 *
 *  - payments — tabloya hiç index eklenmemişti (allocations tablosu OK).
 *    Cari geçmişi ve tarih aralığı sorguları için 2 composite.
 *  - journal_entry_lines — sadece (tenant_id, account_id) vardı; entry'nin
 *    satırlarını almak için (tenant_id, journal_entry_id) eklenir.
 *  - expenses.expense_category_id — kategoriye göre masraf raporu / rollup
 *    sorguları için.
 *  - stock_moves.(tenant_id, created_at) — zaman-aralıklı stok hareketi
 *    raporları için (kar-zarar, dönem sonu envanter).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->index(['tenant_id', 'partner_id'], 'payments_tenant_partner_idx');
                $table->index(['tenant_id', 'payment_date'], 'payments_tenant_date_idx');
                $table->index(['tenant_id', 'journal_id'], 'payments_tenant_journal_idx');
            });
        }

        if (Schema::hasTable('journal_entry_lines')) {
            Schema::table('journal_entry_lines', function (Blueprint $table) {
                $table->index(['tenant_id', 'journal_entry_id'], 'jel_tenant_entry_idx');
            });
        }

        if (Schema::hasTable('expenses')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->index(['tenant_id', 'expense_category_id'], 'expenses_tenant_category_idx');
            });
        }

        if (Schema::hasTable('stock_moves')) {
            Schema::table('stock_moves', function (Blueprint $table) {
                $table->index(['tenant_id', 'created_at'], 'stock_moves_tenant_created_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropIndex('payments_tenant_partner_idx');
                $table->dropIndex('payments_tenant_date_idx');
                $table->dropIndex('payments_tenant_journal_idx');
            });
        }
        if (Schema::hasTable('journal_entry_lines')) {
            Schema::table('journal_entry_lines', function (Blueprint $table) {
                $table->dropIndex('jel_tenant_entry_idx');
            });
        }
        if (Schema::hasTable('expenses')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->dropIndex('expenses_tenant_category_idx');
            });
        }
        if (Schema::hasTable('stock_moves')) {
            Schema::table('stock_moves', function (Blueprint $table) {
                $table->dropIndex('stock_moves_tenant_created_idx');
            });
        }
    }
};
