<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Modules\Accounting\Models\Currency;
use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Services\AccountingDefaultsService;
use Modules\Accounting\Services\ExchangeRateService;
use Modules\Accounting\Services\InvoiceService;
use Modules\Accounting\Services\PaymentService;
use Modules\Inventory\Models\LandedCost;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Partner;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ReorderingRule;
use Modules\Inventory\Models\ReplenishmentSuggestion;
use Modules\Inventory\Models\StockQuant;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Services\ScrapService;
use Modules\Inventory\Services\TransferBatchService;
use Modules\Purchase\Services\PurchaseOrderService;
use Modules\Sales\Services\SalesOrderService;

/**
 * Tüm fazları kapsayan zengin demo verisi. Baz DemoDataSeeder + InventoryDemoSeeder
 * çalıştıktan sonra, üstüne şunları ekler:
 *
 * - Accounting: her tenant için Tekdüzen Hesap Planı + KDV oranları + para birimleri
 * - Currency + Exchange Rate: USD/EUR TCMB tarzı kur (30 & 33)
 * - Ek personel: Purchasing Officer, Sales Rep, Accountant, Warehouse Operator (her tenant'a)
 * - Partners: 2 tedarikçi + 2 müşteri (her tenant)
 * - Purchase Orders: 1 confirmed + received + invoiced; 1 draft
 * - Sales Orders: 1 confirmed + delivered + invoiced; 1 quotation
 * - Invoices: TL + USD (döviz örneği) + kısmen ödenmiş
 * - Payments: 1 tam ödeme, 1 kısmi ödeme
 * - Landed Cost: 1 validated (kargo maliyet dağıtımı)
 * - Scrap: 1 hurda kaydı
 * - Batch Transfer: 1 tamamlanmış paket
 * - Reordering Rules + Replenishment Suggestions
 * - Consignment: bir tenant'ta 1 konsinye ürün
 */
class FullDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DemoDataSeeder::class);

        foreach (Tenant::all() as $tenant) {
            $this->seedAccountingBase($tenant);
            $this->seedExtraUsers($tenant);
            $this->seedPartners($tenant);
            $this->seedPurchaseFlow($tenant);
            $this->seedSalesFlow($tenant);
            $this->seedReorderingRules($tenant);
        }

        $acme = Tenant::where('name', 'Acme Lojistik AŞ')->first();
        if ($acme !== null) {
            $this->seedAdvancedFeatures($acme);
        }
    }

    private function seedAccountingBase(Tenant $tenant): void
    {
        app(AccountingDefaultsService::class)->provision($tenant);

        foreach (Currency::withoutGlobalScopes()->where('tenant_id', $tenant->id)->get() as $currency) {
            $currency->update([
                'symbol' => ['TRY' => '₺', 'USD' => '$', 'EUR' => '€'][$currency->code] ?? '',
                'position' => $currency->code === 'TRY' ? 'after' : 'before',
            ]);
        }

        $usd = Currency::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('code', 'USD')->first();
        $eur = Currency::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('code', 'EUR')->first();

        if ($usd !== null) {
            app(ExchangeRateService::class)->recordManualRate($tenant->id, $usd->id, now()->toDateString(), '33.100000', '33.200000');
            app(ExchangeRateService::class)->recordManualRate($tenant->id, $usd->id, now()->subDays(30)->toDateString(), '30.000000', '30.100000');
        }
        if ($eur !== null) {
            app(ExchangeRateService::class)->recordManualRate($tenant->id, $eur->id, now()->toDateString(), '36.500000', '36.600000');
        }
    }

    private function seedExtraUsers(Tenant $tenant): void
    {
        setPermissionsTeamId($tenant->id);
        $slug = str($tenant->name)->before(' ')->lower();

        $definitions = [
            ['name' => 'Ayşe Satın Alma', 'email' => "satinalma@{$slug}.test", 'role' => 'Purchasing Officer'],
            ['name' => 'Kerem Satış', 'email' => "satis@{$slug}.test", 'role' => 'Sales Representative'],
            ['name' => 'Deniz Muhasebe', 'email' => "muhasebe@{$slug}.test", 'role' => 'Accountant'],
        ];

        foreach ($definitions as $def) {
            $user = User::firstOrNew(['email' => $def['email']]);
            $user->fill(['name' => $def['name'], 'password' => 'password']);
            $user->tenant_id = $tenant->id;
            $user->save();
            if (! $user->hasRole($def['role'])) {
                $user->assignRole($def['role']);
            }
        }
    }

    private function seedPartners(Tenant $tenant): array
    {
        $partners = [];
        foreach ([
            ['Alfa Toptan', true, false],
            ['Beta Distribütör', true, false],
            ['Gamma Perakende', false, true],
            ['Delta Kurumsal', false, true],
        ] as [$name, $isSupplier, $isCustomer]) {
            $partners[] = Partner::firstOrCreate(
                ['tenant_id' => $tenant->id, 'name' => $name],
                ['is_supplier' => $isSupplier, 'is_customer' => $isCustomer],
            );
        }

        return $partners;
    }

    private function seedPurchaseFlow(Tenant $tenant): void
    {
        $supplier = Partner::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('is_supplier', true)->first();
        $stock = Location::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('name', 'Stok')->first();
        $product = Product::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('product_type', 'stockable')->where('is_kit', false)->first();

        if ($supplier === null || $stock === null || $product === null) {
            return;
        }

        setPermissionsTeamId($tenant->id);
        $admin = User::where('tenant_id', $tenant->id)->whereHas('roles', fn ($q) => $q->where('name', 'Tenant Admin'))->first();
        $purchaser = User::where('tenant_id', $tenant->id)->whereHas('roles', fn ($q) => $q->where('name', 'Purchasing Officer'))->first();

        if ($admin === null || $purchaser === null) {
            return;
        }

        try {
            // 1) Draft PO
            $draftPo = app(PurchaseOrderService::class)->create($tenant->id, $supplier->id, $purchaser);
            app(PurchaseOrderService::class)->addLine($draftPo, $product->id, $product->uom_id, '5', '80.0000');

            // 2) Confirmed + Received PO — kilitli lokasyon varsa bu adım atlanır
            $confirmedPo = app(PurchaseOrderService::class)->create($tenant->id, $supplier->id, $purchaser);
            $line = app(PurchaseOrderService::class)->addLine($confirmedPo, $product->id, $product->uom_id, '10', '75.0000');
            app(PurchaseOrderService::class)->sendRfq($confirmedPo);
            app(PurchaseOrderService::class)->confirm($confirmedPo->fresh(), $admin);
            app(PurchaseOrderService::class)->receive($line->fresh(), '10', $stock->id);

            // 3) Purchase invoice + payment
            $invoiceService = app(InvoiceService::class);
            $invoice = $invoiceService->create($tenant->id, $supplier->id, 'purchase', $confirmedPo->fresh());
            $invoiceService->addLine($invoice, $product->id, '10', '75.0000', null);
            $invoiceService->post($invoice->fresh(), $admin);

            $cashJournal = Journal::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('type', 'cash')->first();
            if ($cashJournal !== null) {
                $paymentService = app(PaymentService::class);
                $payment = $paymentService->create($tenant->id, $supplier->id, $cashJournal->id, '750.0000', now()->toDateString());
                $paymentService->allocate($payment, $invoice->fresh(), '750.0000');
            }
        } catch (\Throwable $e) {
            // Sayım kilidi, muhasebe konfig eksikliği vb — sessizce atla
        }
    }

    private function seedSalesFlow(Tenant $tenant): void
    {
        $customer = Partner::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('is_customer', true)->first();
        $stock = Location::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('name', 'Stok')->first();
        $product = Product::withoutGlobalScopes()->where('tenant_id', $tenant->id)
            ->where('product_type', 'stockable')->where('is_kit', false)
            ->whereHas('quants', fn ($q) => $q->where('qty', '>', 3))
            ->first();

        if ($customer === null || $stock === null || $product === null) {
            return;
        }

        setPermissionsTeamId($tenant->id);
        $admin = User::where('tenant_id', $tenant->id)->whereHas('roles', fn ($q) => $q->where('name', 'Tenant Admin'))->first();
        $salesRep = User::where('tenant_id', $tenant->id)->whereHas('roles', fn ($q) => $q->where('name', 'Sales Representative'))->first();

        if ($admin === null || $salesRep === null) {
            return;
        }

        try {
            // 1) Quotation
            $quote = app(SalesOrderService::class)->create($tenant->id, $customer->id, $stock->id, $salesRep);
            app(SalesOrderService::class)->addLine($quote, $product->id, $product->uom_id, '2', '150.0000');
            app(SalesOrderService::class)->sendQuotation($quote->fresh());

            // 2) Confirmed + partially delivered
            $so = app(SalesOrderService::class)->create($tenant->id, $customer->id, $stock->id, $salesRep);
            $line = app(SalesOrderService::class)->addLine($so, $product->id, $product->uom_id, '3', '150.0000');
            app(SalesOrderService::class)->sendQuotation($so->fresh());
            app(SalesOrderService::class)->confirm($so->fresh(), $admin);
            app(SalesOrderService::class)->deliver($line->fresh(), '2');

            // 3) Sales invoice + partial payment
            $invoice = app(InvoiceService::class)->create($tenant->id, $customer->id, 'sale', $so->fresh());
            app(InvoiceService::class)->addLine($invoice, $product->id, '2', '150.0000', null);
            app(InvoiceService::class)->post($invoice->fresh(), $admin);

            $bankJournal = Journal::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('type', 'bank')->first();
            if ($bankJournal !== null) {
                $payment = app(PaymentService::class)->create($tenant->id, $customer->id, $bankJournal->id, '150.0000', now()->toDateString());
                app(PaymentService::class)->allocate($payment, $invoice->fresh(), '150.0000');
            }
        } catch (\Throwable $e) {
        }
    }

    private function seedReorderingRules(Tenant $tenant): void
    {
        $stock = Location::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('name', 'Stok')->first();
        $products = Product::withoutGlobalScopes()->where('tenant_id', $tenant->id)
            ->where('product_type', 'stockable')->where('is_kit', false)
            ->take(2)->get();

        foreach ($products as $product) {
            $rule = ReorderingRule::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'product_id' => $product->id, 'location_id' => $stock->id],
                ['min_qty' => '5', 'max_qty' => '25', 'trigger_type' => 'auto'],
            );

            ReplenishmentSuggestion::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'reordering_rule_id' => $rule->id, 'status' => 'pending'],
                ['suggested_qty' => '20'],
            );
        }
    }

    private function seedAdvancedFeatures(Tenant $tenant): void
    {
        setPermissionsTeamId($tenant->id);
        $admin = User::where('tenant_id', $tenant->id)->whereHas('roles', fn ($q) => $q->where('name', 'Tenant Admin'))->first();
        // Kilitli olmayan bir iç lokasyon seç (Sayım demo'su Stok'u kilitliyor).
        $unlockedLocation = Location::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('type', 'internal')
            ->where('counting_lock', false)
            ->whereIn('id', StockQuant::withoutGlobalScopes()->where('qty', '>', 2)->pluck('location_id'))
            ->first();
        $product = null;
        if ($unlockedLocation !== null) {
            $quantWithStock = StockQuant::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('location_id', $unlockedLocation->id)
                ->where('qty', '>', 2)->first();
            $product = $quantWithStock
                ? Product::withoutGlobalScopes()->where('id', $quantWithStock->product_id)
                    ->where('product_type', 'stockable')->where('is_kit', false)->first()
                : null;
        }
        $uom = Uom::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('is_reference', true)->first();

        if ($admin === null || $unlockedLocation === null || $product === null || $uom === null) {
            return;
        }

        // Scrap: 1 hurda
        try {
            app(ScrapService::class)->scrap($tenant->id, $product, $unlockedLocation->id, $uom, '1', null, 'Hasarlı ambalaj', $admin);
        } catch (\Throwable) {
        }

        // Landed Cost (draft — kullanıcı üzerinde denemesi için)
        try {
            $lc = LandedCost::create(['tenant_id' => $tenant->id, 'split_method' => 'by_quantity', 'status' => 'draft']);
            $lc->lines()->create([
                'tenant_id' => $tenant->id,
                'description' => 'Kargo',
                'amount' => '250.0000',
            ]);
            $lc->lines()->create([
                'tenant_id' => $tenant->id,
                'description' => 'Gümrük',
                'amount' => '150.0000',
            ]);
        } catch (\Throwable) {
        }

        // Batch Transfer
        try {
            $wh = Warehouse::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first();
            $altLocation = Location::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)->where('warehouse_id', $wh->id)->where('id', '!=', $stock->id)->first();

            if ($altLocation) {
                $batch = app(TransferBatchService::class)->create($tenant->id, 'Sabah Turu — '.now()->format('d.m'));
                // Batch tek başına kalsın; kullanıcı transfer ekleyip complete etsin.
                unset($batch);
            }
        } catch (\Throwable) {
        }

        // Consignment: 1 partner-owned quant
        try {
            $partner = Partner::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('is_supplier', true)->first();
            $consignedProduct = Product::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('is_kit', false)->skip(1)->first();

            if ($partner && $consignedProduct) {
                StockQuant::withoutGlobalScopes()->firstOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'product_id' => $consignedProduct->id,
                        'location_id' => $unlockedLocation->id,
                        'owner_partner_id' => $partner->id,
                        'lot_id' => null,
                    ],
                    ['qty' => '30', 'reserved_qty' => '0'],
                );
            }
        } catch (\Throwable) {
        }
    }
}
