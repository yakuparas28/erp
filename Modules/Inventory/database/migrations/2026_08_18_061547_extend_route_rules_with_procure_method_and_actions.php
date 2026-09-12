<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Odoo `stock.rule` denkliği: rota kuralının action alanı 4 değer taşır
 *   - push   : ilgili location'a ulaşan mal otomatik bir sonraki adıma iletilir
 *   - pull   : ihtiyaç doğunca kural tetiklenir, bir önceki adım tamamlanır
 *   - manufacture : üretim emri oluşturulur (ileride MRP faz için no-op)
 *   - buy    : tedarikçiye satınalma ihtiyacı düşülür (replenishment_suggestions)
 * procure_method:
 *   - make_to_stock: mevcut stoktan tüketilir
 *   - make_to_order: her ihtiyaç kendi tedarik zincirini tetikler
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // SQLite enum CHECK constraint'i sadece CREATE TABLE'da ekleniyor;
            // enum'ı genişletmek için tabloyu yeniden yaratmak gerek. Basitleştirmek
            // için action'ı string'e çeviriyoruz (uygulama katmanı zaten validate ediyor).
            DB::statement('CREATE TABLE route_rules_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                tenant_id INTEGER NOT NULL,
                route_id INTEGER NOT NULL,
                from_location_id INTEGER NOT NULL,
                to_location_id INTEGER NOT NULL,
                action VARCHAR NOT NULL,
                procure_method VARCHAR NOT NULL DEFAULT "make_to_stock",
                name VARCHAR NULL,
                sequence INTEGER NOT NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL
            )');
            DB::statement('INSERT INTO route_rules_new (id, tenant_id, route_id, from_location_id, to_location_id, action, sequence, created_at, updated_at) SELECT id, tenant_id, route_id, from_location_id, to_location_id, action, sequence, created_at, updated_at FROM route_rules');
            DB::statement('DROP TABLE route_rules');
            DB::statement('ALTER TABLE route_rules_new RENAME TO route_rules');
            DB::statement('CREATE INDEX route_rules_tenant_route_seq_idx ON route_rules (tenant_id, route_id, sequence)');

            return;
        }

        DB::statement("ALTER TABLE route_rules MODIFY COLUMN action ENUM('push','pull','manufacture','buy') NOT NULL");

        Schema::table('route_rules', function (Blueprint $table): void {
            $table->enum('procure_method', ['make_to_stock', 'make_to_order'])->default('make_to_stock')->after('action');
            $table->string('name')->nullable()->after('procure_method');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('route_rules', function (Blueprint $table): void {
                $table->dropColumn(['procure_method', 'name']);
            });

            return;
        }

        Schema::table('route_rules', function (Blueprint $table): void {
            $table->dropColumn(['procure_method', 'name']);
        });

        DB::statement("ALTER TABLE route_rules MODIFY COLUMN action ENUM('push','pull') NOT NULL");
    }
};
