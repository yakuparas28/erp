<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Modules\Inventory\Jobs\GenerateInstantVariants;
use Modules\Inventory\Models\InventoryAdjustment;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductAttribute;
use Modules\Inventory\Models\ProductAttributeValue;
use Modules\Inventory\Models\ProductBarcode;
use Modules\Inventory\Models\ProductCategory;
use Modules\Inventory\Models\ProductKitComponent;
use Modules\Inventory\Models\ProductLot;
use Modules\Inventory\Models\ProductTemplate;
use Modules\Inventory\Models\ProductTemplateAttributeLine;
use Modules\Inventory\Models\StockQuant;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Models\WarehouseTransfer;
use Modules\Inventory\Models\WarehouseTransferLine;
use Modules\Inventory\Services\InventoryAdjustmentService;
use Modules\Inventory\Services\InventoryDefaultsService;
use Modules\Inventory\Services\StockMoveService;
use Modules\Inventory\Services\VariantGeneratorService;
use Modules\Inventory\Services\WarehouseTransferService;

/**
 * Geliştirme/test ortamı için ekranlarda gezinip deneyebileceğiniz zengin
 * envanter verisi üretir: depo/lokasyon, birimler, kategoriler, ürünler
 * (stoklu/sarf/hizmet), varyantlı şablon, kit, lot takibi, stok hareketleri,
 * onaylanmış bir sayım fişi, devam eden (kilitli) bir sayım fişi ve
 * tamamlanmış bir transfer. Üretimde çalıştırılmaz.
 */
class InventoryDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedAcme();
        $this->seedDemoTicaret();
        $this->seedTestGida();
    }

    private function seedAcme(): void
    {
        $tenant = Tenant::where('name', 'Acme Lojistik AŞ')->firstOrFail();
        app(InventoryDefaultsService::class)->provision($tenant);

        $mainLocation = Location::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)->where('name', 'Stok')->firstOrFail();

        $warehouse2 = Warehouse::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'WH2'],
            ['name' => 'İkinci Depo'],
        );
        $shelfA = Location::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'warehouse_id' => $warehouse2->id, 'name' => 'Raf A'],
            ['type' => 'internal'],
        );

        $unitCategory = UomCategory::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();
        $unit = Uom::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('is_reference', true)->firstOrFail();
        $box = Uom::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'uom_category_id' => $unitCategory->id, 'name' => 'Koli (12li)'],
            ['factor' => '12.000000', 'is_reference' => false],
        );

        $electronics = ProductCategory::withoutGlobalScopes()->firstOrCreate(['tenant_id' => $tenant->id, 'name' => 'Elektronik']);

        $headphone = Product::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Kablosuz Kulaklık'],
            ['uom_id' => $unit->id, 'product_category_id' => $electronics->id, 'sku' => 'ELK-001', 'product_type' => 'stockable', 'track_by' => 'none'],
        );
        ProductBarcode::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'barcode' => '8690000000011'],
            ['product_id' => $headphone->id],
        );
        ProductBarcode::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'barcode' => '8690000000028'],
            ['product_id' => $headphone->id, 'uom_id' => $box->id],
        );

        $cable = Product::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Şarj Kablosu'],
            ['uom_id' => $unit->id, 'product_category_id' => $electronics->id, 'sku' => 'ELK-002', 'product_type' => 'stockable', 'track_by' => 'none'],
        );

        $kit = Product::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Kulaklık Başlangıç Seti', 'is_kit' => true],
            ['uom_id' => $unit->id, 'product_category_id' => $electronics->id, 'sku' => 'ELK-KIT-001', 'product_type' => 'stockable', 'track_by' => 'none'],
        );
        ProductKitComponent::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'kit_product_id' => $kit->id, 'component_product_id' => $headphone->id],
            ['qty' => '1'],
        );
        ProductKitComponent::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'kit_product_id' => $kit->id, 'component_product_id' => $cable->id],
            ['qty' => '1'],
        );

        Product::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Kurulum ve Danışmanlık Hizmeti'],
            ['uom_id' => $unit->id, 'sku' => 'HIZ-001', 'product_type' => 'service', 'cost_method' => 'standard', 'standard_cost' => '250.0000', 'track_by' => 'none'],
        );
        Product::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Ambalaj Bandı'],
            ['uom_id' => $unit->id, 'sku' => 'SARF-001', 'product_type' => 'consumable', 'track_by' => 'none'],
        );

        // Varyantlı şablon: Tişört — Renk x Beden (instant)
        $template = ProductTemplate::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Pamuklu Tişört'],
            ['base_price' => '150.0000'],
        );
        $color = ProductAttribute::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Renk'],
            ['creation_mode' => 'instant'],
        );
        $red = ProductAttributeValue::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'product_attribute_id' => $color->id, 'value' => 'Kırmızı'],
            ['price_extra' => '5.0000'],
        );
        $blue = ProductAttributeValue::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'product_attribute_id' => $color->id, 'value' => 'Mavi'],
            ['price_extra' => '0.0000'],
        );
        $size = ProductAttribute::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Beden'],
            ['creation_mode' => 'instant'],
        );
        foreach (['S', 'M', 'L'] as $sizeValue) {
            ProductAttributeValue::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'product_attribute_id' => $size->id, 'value' => $sizeValue],
            );
        }

        $generator = app(VariantGeneratorService::class);
        foreach ([$color, $size] as $attribute) {
            if (! ProductTemplateAttributeLine::withoutGlobalScopes()
                ->where('product_template_id', $template->id)->where('product_attribute_id', $attribute->id)->exists()) {
                $generator->attachAttribute($template, $attribute);
                // QUEUE_CONNECTION=database olduğundan demo verisinin anında
                // görünmesi için job'ı ayrıca senkron çalıştırıyoruz.
                (new GenerateInstantVariants($tenant->id, $template->id))->handle();
            }
        }

        // Stok hareketleri
        $moves = app(StockMoveService::class);
        $this->stockIfEmpty($moves, $tenant->id, $headphone, $mainLocation->id, '80', $unit);
        $this->stockIfEmpty($moves, $tenant->id, $cable, $mainLocation->id, '150', $unit);

        // Tamamlanmış transfer: ana lokasyondan Raf A'ya 10 kulaklık
        if (! StockQuant::withoutGlobalScopes()->where('product_id', $headphone->id)->where('location_id', $shelfA->id)->exists()) {
            $transfer = WarehouseTransfer::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id, 'from_location_id' => $mainLocation->id, 'to_location_id' => $shelfA->id,
                'created_by' => User::where('email', 'ali@acmelojistik.test')->firstOrFail()->id, 'status' => 'draft',
            ]);
            WarehouseTransferLine::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id, 'warehouse_transfer_id' => $transfer->id,
                'product_id' => $headphone->id, 'uom_id' => $unit->id, 'qty' => '10',
            ]);
            app(WarehouseTransferService::class)->complete($transfer);
        }

        // Onaylanmış geçmiş sayım fişi (Raf A'da +2 kulaklık düzeltmesi)
        $admin = User::where('email', 'ali@acmelojistik.test')->firstOrFail();
        $approver = User::withoutGlobalScopes()->firstOrCreate(
            ['email' => 'depo.sorumlusu@acmelojistik.test'],
            ['name' => 'Depo Sorumlusu', 'tenant_id' => $tenant->id, 'password' => 'password'],
        );
        setPermissionsTeamId($tenant->id);
        if (! $approver->hasRole('Tenant Admin')) {
            $approver->assignRole('Tenant Admin');
        }

        if (InventoryAdjustment::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('location_id', $shelfA->id)->doesntExist()) {
            $adjustments = app(InventoryAdjustmentService::class);
            $adjustment = $adjustments->open($tenant->id, $shelfA->id, $admin);
            $adjustments->startCounting($adjustment);
            $adjustments->addCount($adjustment, $headphone->id, '12', null);
            $adjustments->submitForApproval($adjustment);
            $adjustments->approve($adjustment->fresh(), $approver);
        }

        // Devam eden (kilitli) sayım fişi: ana lokasyon — UI'da kilit rozetini göstermek için
        if (InventoryAdjustment::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('status', 'counting')->doesntExist()) {
            $adjustments = app(InventoryAdjustmentService::class);
            $ongoing = $adjustments->open($tenant->id, $mainLocation->id, $admin);
            $adjustments->startCounting($ongoing);
            $adjustments->addCount($ongoing, $cable->id, '145', null);
        }
    }

    private function seedDemoTicaret(): void
    {
        $tenant = Tenant::where('name', 'Demo Ticaret Ltd')->firstOrFail();
        app(InventoryDefaultsService::class)->provision($tenant);

        $location = Location::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('name', 'Stok')->firstOrFail();
        $unit = Uom::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('is_reference', true)->firstOrFail();
        $category = ProductCategory::withoutGlobalScopes()->firstOrCreate(['tenant_id' => $tenant->id, 'name' => 'Ofis Malzemeleri']);

        $paper = Product::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'A4 Fotokopi Kağıdı'],
            ['uom_id' => $unit->id, 'product_category_id' => $category->id, 'sku' => 'OFS-001', 'product_type' => 'consumable', 'track_by' => 'none'],
        );
        $pen = Product::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Tükenmez Kalem'],
            ['uom_id' => $unit->id, 'product_category_id' => $category->id, 'sku' => 'OFS-002', 'product_type' => 'consumable', 'track_by' => 'none'],
        );

        $moves = app(StockMoveService::class);
        $this->stockIfEmpty($moves, $tenant->id, $paper, $location->id, '200', $unit);
        $this->stockIfEmpty($moves, $tenant->id, $pen, $location->id, '500', $unit);

        // İkinci kullanıcı: Warehouse Operator (rol/izin ekranlarını test için)
        $operator = User::withoutGlobalScopes()->firstOrCreate(
            ['email' => 'depo@demoticaret.test'],
            ['name' => 'Depo Elemanı', 'tenant_id' => $tenant->id, 'password' => 'password'],
        );
        setPermissionsTeamId($tenant->id);
        if (! $operator->hasRole('Warehouse Operator')) {
            $operator->assignRole('Warehouse Operator');
        }
    }

    private function seedTestGida(): void
    {
        $tenant = Tenant::where('name', 'Test Gıda Sanayi AŞ')->firstOrFail();
        app(InventoryDefaultsService::class)->provision($tenant);

        $location = Location::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('name', 'Stok')->firstOrFail();
        $unit = Uom::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('is_reference', true)->firstOrFail();
        $category = ProductCategory::withoutGlobalScopes()->firstOrCreate(['tenant_id' => $tenant->id, 'name' => 'Süt Ürünleri']);

        // Lot/SKT takipli ürün — gıda sektörü senaryosu (PRD 3.4)
        $yogurt = Product::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Yoğurt (1kg)'],
            ['uom_id' => $unit->id, 'product_category_id' => $category->id, 'sku' => 'SUT-001', 'product_type' => 'stockable', 'track_by' => 'lot'],
        );

        $lotNear = ProductLot::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'product_id' => $yogurt->id, 'lot_number' => 'LOT-2026-07'],
            ['expiry_date' => now()->addDays(10)->toDateString()],
        );
        $lotFar = ProductLot::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'product_id' => $yogurt->id, 'lot_number' => 'LOT-2026-08'],
            ['expiry_date' => now()->addDays(40)->toDateString()],
        );

        $moves = app(StockMoveService::class);
        foreach ([[$lotNear, '30'], [$lotFar, '50']] as [$lot, $qty]) {
            if (StockQuant::withoutGlobalScopes()
                ->where('product_id', $yogurt->id)->where('lot_id', $lot->id)->doesntExist()) {
                $moves->move(
                    tenantId: $tenant->id, product: $yogurt,
                    fromLocationId: null, toLocationId: $location->id,
                    qty: $qty, uom: $unit, lotId: $lot->id,
                    referenceType: 'inventory_adjustment', referenceId: 700 + $lot->id,
                );
            }
        }

        Product::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Peynir (500g)'],
            ['uom_id' => $unit->id, 'product_category_id' => $category->id, 'sku' => 'SUT-002', 'product_type' => 'stockable', 'track_by' => 'none'],
        );

        $operator = User::withoutGlobalScopes()->firstOrCreate(
            ['email' => 'depo@testgida.test'],
            ['name' => 'Saha Elemanı', 'tenant_id' => $tenant->id, 'password' => 'password'],
        );
        setPermissionsTeamId($tenant->id);
        if (! $operator->hasRole('Warehouse Operator')) {
            $operator->assignRole('Warehouse Operator');
        }
    }

    private function stockIfEmpty(StockMoveService $moves, int $tenantId, Product $product, int $locationId, string $qty, Uom $unit): void
    {
        $exists = StockQuant::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)->where('product_id', $product->id)->where('location_id', $locationId)->exists();

        if ($exists) {
            return;
        }

        $moves->move(
            tenantId: $tenantId, product: $product,
            fromLocationId: null, toLocationId: $locationId,
            qty: $qty, uom: $unit,
            referenceType: 'inventory_adjustment', referenceId: 999,
        );
    }
}
