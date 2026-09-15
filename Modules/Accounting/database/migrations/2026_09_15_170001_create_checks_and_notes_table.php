<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Çek / Senet Portföyü (TR muhasebe pratiği). Alınan + verilen aynı
 * tabloda `direction` ile ayrılır. Statü değişimleri servis katmanında
 * kontrol edilir (portfolio → endorsed/sent_to_bank/collected/bounced/
 * cancelled ya da paid). Her transition JE üretir (dr/cr 101/103/108/
 * 120/121/321).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checks_and_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('instrument_no', 64);
            $table->enum('instrument_type', ['check', 'promissory_note']);
            $table->enum('direction', ['incoming', 'outgoing']);
            $table->unsignedBigInteger('partner_id');
            $table->string('drawer_name', 128)->nullable();
            $table->string('drawee_bank_name', 128)->nullable();
            $table->string('drawee_branch', 128)->nullable();
            $table->date('issue_date');
            $table->date('maturity_date');
            $table->decimal('amount', 15, 4);
            $table->unsignedBigInteger('currency_id')->nullable();
            $table->enum('status', ['portfolio', 'endorsed', 'sent_to_bank', 'collected', 'bounced', 'cancelled', 'paid']);
            $table->unsignedBigInteger('endorsed_to_partner_id')->nullable();
            $table->unsignedBigInteger('collection_bank_journal_id')->nullable();
            $table->date('status_changed_at')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'direction', 'status'], 'cn_tenant_dir_status_idx');
            $table->index(['tenant_id', 'maturity_date'], 'cn_tenant_maturity_idx');
            $table->index(['tenant_id', 'partner_id'], 'cn_tenant_partner_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checks_and_notes');
    }
};
