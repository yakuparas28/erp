<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * POS terminal tanımları ve kart tahsilatları.
 *
 * pos_terminals: tenant başına birden çok POS (ör. Vakıfbank-1, Garanti-1).
 *   commission_rates JSON: {"1": "1.5", "3": "2.5", "6": "3.5"} — taksit
 *   sayısı → komisyon oranı yüzdesi. settlement_days = tahsilatın bankaya
 *   düşme süresi (valör).
 *
 * card_payments: her POS tahsilatı; komisyon+net otomatik hesaplanır.
 *   status pending_settlement iken 108 blokeli hesapta, settle olduğunda
 *   bankaya (102) transfer + komisyonu 653 giderine yazan JE üretilir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_terminals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name', 128);
            $table->unsignedBigInteger('bank_journal_id');
            $table->json('commission_rates');
            $table->integer('settlement_days')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active'], 'pos_terminals_tenant_active_idx');
        });

        Schema::create('card_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('pos_terminal_id');
            $table->unsignedBigInteger('partner_id');
            $table->decimal('gross_amount', 15, 4);
            $table->integer('installments')->default(1);
            $table->decimal('commission_rate', 6, 3);
            $table->decimal('commission_amount', 15, 4);
            $table->decimal('net_amount', 15, 4);
            $table->date('transaction_date');
            $table->date('expected_settlement_date');
            $table->enum('status', ['pending_settlement', 'settled', 'cancelled'])->default('pending_settlement');
            $table->date('settled_at')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status'], 'card_payments_tenant_status_idx');
            $table->index(['tenant_id', 'partner_id'], 'card_payments_tenant_partner_idx');
            $table->index(['tenant_id', 'expected_settlement_date'], 'card_payments_tenant_settle_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_payments');
        Schema::dropIfExists('pos_terminals');
    }
};
