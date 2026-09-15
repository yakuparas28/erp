<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Banka ekstresi import + mutabakat.
 *
 * bank_statements: her upload bir ekstre kaydı. journal (banka) + dönem
 * (from_date, to_date) + upload eden + orijinal dosya adı + hash (aynı
 * dosyanın iki kez import edilmesini engeller).
 *
 * bank_statement_lines: ekstredeki her satır. Otomatik veya manuel
 * eşleştirme sonucu payment/check/card_payment/journal_entry'ye
 * bağlanır (matched_type + matched_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_statements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('bank_journal_id');
            $table->string('original_filename', 255)->nullable();
            $table->string('file_hash', 64)->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->decimal('opening_balance', 15, 4)->default(0);
            $table->decimal('closing_balance', 15, 4)->default(0);
            $table->integer('total_lines')->default(0);
            $table->integer('matched_lines')->default(0);
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'bank_journal_id'], 'bs_tenant_bank_idx');
            $table->unique(['tenant_id', 'bank_journal_id', 'file_hash'], 'bs_dedup_uk');
        });

        Schema::create('bank_statement_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('bank_statement_id');
            $table->date('transaction_date');
            $table->string('description', 512);
            $table->decimal('debit', 15, 4)->default(0);
            $table->decimal('credit', 15, 4)->default(0);
            $table->decimal('running_balance', 15, 4)->nullable();
            $table->string('external_ref', 128)->nullable();
            $table->string('matched_type', 40)->nullable();
            $table->unsignedBigInteger('matched_id')->nullable();
            $table->enum('status', ['unmatched', 'auto_matched', 'manual_matched', 'ignored'])->default('unmatched');
            $table->timestamps();

            $table->index(['tenant_id', 'bank_statement_id'], 'bsl_tenant_stmt_idx');
            $table->index(['tenant_id', 'status'], 'bsl_tenant_status_idx');
            $table->index(['tenant_id', 'matched_type', 'matched_id'], 'bsl_tenant_match_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_statement_lines');
        Schema::dropIfExists('bank_statements');
    }
};
