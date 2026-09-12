<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Odoo `sale.order.validity_date` + `date_sent` + partner iletişim alanları.
 * Teklif Gönder aksiyonu artık sent_at kaydeder ve isteğe bağlı geçerlilik
 * tarihi ile birlikte müşteriye e-posta gönderebilir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table): void {
            $table->timestamp('sent_at')->nullable()->after('status');
            $table->date('validity_date')->nullable()->after('sent_at');
        });

        Schema::table('partners', function (Blueprint $table): void {
            if (! Schema::hasColumn('partners', 'email')) {
                $table->string('email')->nullable()->after('name');
            }
            if (! Schema::hasColumn('partners', 'phone')) {
                $table->string('phone')->nullable()->after('email');
            }
            if (! Schema::hasColumn('partners', 'address')) {
                $table->text('address')->nullable()->after('phone');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table): void {
            $table->dropColumn(['sent_at', 'validity_date']);
        });

        Schema::table('partners', function (Blueprint $table): void {
            foreach (['email', 'phone', 'address'] as $col) {
                if (Schema::hasColumn('partners', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
