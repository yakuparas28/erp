<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Odoo'nun res.currency.rate modelinde `check rate>0` SQL kısıtı var. MySQL 8+
 * `ALTER TABLE ADD CONSTRAINT CHECK` destekliyor; SQLite CHECK constraint'i
 * yalnız CREATE TABLE'da ekleyebildiği için test ortamında (SQLite) atlanır.
 * Uygulama katmanı `gt:0` validation ile zaten kur pozitifliğini garanti
 * ediyor — bu DB constraint'i doğrudan SQL enjeksiyonlarına karşı savunma.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE exchange_rates ADD CONSTRAINT exchange_rates_buy_rate_positive CHECK (buy_rate > 0)');
        DB::statement('ALTER TABLE exchange_rates ADD CONSTRAINT exchange_rates_sell_rate_positive CHECK (sell_rate > 0)');
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE exchange_rates DROP CONSTRAINT exchange_rates_buy_rate_positive');
        DB::statement('ALTER TABLE exchange_rates DROP CONSTRAINT exchange_rates_sell_rate_positive');
    }
};
