<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Odoo `stock.scrap` denkliği: hasarlı/süresi geçmiş ürünlerin belirli bir
 * kaynak lokasyondan `Scrap` sanal lokasyonuna hareket ettirilmesi. Fire kaydı
 * StockMove ile tutulur (source_type='scrap'); ledger'da kalıcı, ayrı bir
 * `scraps` tablosu insan-okunur log tutar.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // SQLite ENUM'u serbestçe genişletiyor.
        } else {
            DB::statement("ALTER TABLE locations MODIFY COLUMN type ENUM('internal','view','customer','supplier','inventory_loss','transit','scrap') NOT NULL");
        }

        Schema::create('scraps', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('source_location_id')->constrained('locations');
            $table->foreignId('scrap_location_id')->constrained('locations');
            $table->foreignId('lot_id')->nullable()->constrained('product_lots');
            $table->foreignId('uom_id')->constrained('uoms');
            $table->decimal('qty', 12, 4);
            $table->text('reason')->nullable();
            $table->foreignId('done_by_user_id')->constrained('users');
            $table->timestamp('scrapped_at')->useCurrent();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scraps');

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE locations MODIFY COLUMN type ENUM('internal','view','customer','supplier','inventory_loss','transit') NOT NULL");
        }
    }
};
