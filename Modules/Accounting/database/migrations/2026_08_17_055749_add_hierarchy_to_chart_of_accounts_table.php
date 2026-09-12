<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tekdüzen Hesap Planı hiyerarşisi: 3-basamaklı ana hesaplar `is_system=true`
 * (yasal sabit — silinemez, kodu/tipi değiştirilemez); kullanıcı ana hesabın
 * altına 4+ basamaklı alt hesap (`parent_id`) ekler.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chart_of_accounts', function (Blueprint $table): void {
            $table->foreignId('parent_id')->nullable()->after('tenant_id')
                ->constrained('chart_of_accounts')->nullOnDelete();
            $table->boolean('is_system')->after('type')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('chart_of_accounts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn('is_system');
        });
    }
};
