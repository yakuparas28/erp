<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Services\AccountingDefaultsService;
use Modules\Accounting\Services\InvoiceService;
use Modules\Accounting\Services\PaymentService;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Partner;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductCategory;
use Modules\Inventory\Models\StockQuant;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;
use Modules\Inventory\Services\InventoryDefaultsService;
use Modules\Purchase\Models\PurchaseOrder;
use Modules\Purchase\Services\PurchaseOrderService;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Services\SalesOrderService;

/**
 * Test Firma tenant'ı için Cari → Envanter → Satın Alma → Satış → Fatura
 * uçtan uca demo verisi. FLEET/HR seeder'larıyla aynı pattern:
 * idempotent, servisleri kullanır (raw insert değil), tenant scope
 * bağlamında çalışır.
 *
 * Üretilen:
 *  - 4 müşteri + 4 tedarikçi (yeni Cari Kart alanlarıyla: VKN, il, e-fatura,
 *    ödeme vadesi, risk limiti, muhasebe hesap kodu)
 *  - 3 kategori + 5 ürün (stoklu) + başlangıç stoğu
 *  - 4 Satın Alma Siparişi: Taslak / RFQ Gönderildi / Onaylandı+Kısmi Alım /
 *    Tamamlandı + Satın Alma Faturası (Posted)
 *  - 4 Satış Siparişi/Teklif: Taslak Teklif / Gönderilmiş Teklif /
 *    Onaylandı+Kısmi Teslim / Tamamlandı + Satış Faturası (Kısmi Ödenmiş)
 */
class TestFirmaFullFlowSeeder extends Seeder
{
    public function run(): void
    {
        $tenantName = env('FULL_FLOW_TENANT', 'Test Firma');
        $tenant = Tenant::where('name', $tenantName)->firstOrFail();

        setPermissionsTeamId($tenant->id);
        app(InventoryDefaultsService::class)->provision($tenant);
        app(AccountingDefaultsService::class)->provision($tenant);

        [$customers, $suppliers] = $this->seedPartners($tenant);
        $products = $this->seedInventory($tenant);
        $actors = $this->seedActors($tenant);

        $this->seedPurchaseFlow($tenant, $suppliers, $products, $actors);
        $this->seedSalesFlow($tenant, $customers, $products, $actors);

        $this->command?->info('Test Firma full-flow demo seeded (partners + inventory + purchase + sales + invoices).');
    }

    /**
     * @return array{0: array<int, Partner>, 1: array<int, Partner>}
     */
    private function seedPartners(Tenant $tenant): array
    {
        $customers = [];
        $suppliers = [];

        $customerData = [
            ['MUS001', 'Anadolu Tekstil A.Ş.', 'company', '1234567890', 'Kadıköy V.D.', 'İstanbul', 'Kadıköy', 'e_fatura', 30, 250000, 'info@anadolutekstil.com.tr', '0216 555 12 34'],
            ['MUS002', 'Ege Gıda Ltd. Şti.', 'company', '9876543210', 'Konak V.D.', 'İzmir', 'Konak', 'e_arsiv', 60, 500000, 'muhasebe@egegida.com.tr', '0232 445 67 89'],
            ['MUS003', 'Toros İnşaat Malzemeleri', 'company', '5555444433', 'Ankara Yenimahalle V.D.', 'Ankara', 'Yenimahalle', 'e_fatura', 45, 750000, 'satinalma@torosinsaat.com', '0312 222 33 44'],
            ['MUS004', 'Mehmet Yılmaz', 'individual', null, null, 'Bursa', 'Osmangazi', 'e_arsiv', 0, 10000, 'm.yilmaz@example.com', '0532 111 22 33'],
        ];
        foreach ($customerData as [$code, $name, $type, $vkn, $office, $city, $district, $einv, $term, $credit, $email, $phone]) {
            $customers[] = Partner::updateOrCreate(
                ['tenant_id' => $tenant->id, 'partner_code' => $code],
                [
                    'name' => $name,
                    'entity_type' => $type,
                    'tax_number' => $vkn,
                    'tax_office' => $office,
                    'national_id' => $type === 'individual' ? '12345678901' : null,
                    'e_invoice_status' => $einv,
                    'country' => 'Türkiye',
                    'city' => $city,
                    'district' => $district,
                    'email' => $email,
                    'phone' => $phone,
                    'is_customer' => true,
                    'is_supplier' => false,
                    'payment_term_days' => $term,
                    'account_code_receivable' => '120.01.'.$code,
                    'currency_code' => 'TRY',
                    'credit_limit' => $credit,
                    'is_active' => true,
                ],
            );
        }

        $supplierData = [
            ['SAT001', 'Marmara Kağıt A.Ş.', '1111111111', 'Beşiktaş V.D.', 'İstanbul', 'Beşiktaş', 'e_fatura', 45, 'satis@marmarakagit.com.tr', '0212 333 44 55'],
            ['SAT002', 'Akdeniz Kimya Ltd.', '2222222222', 'Antalya V.D.', 'Antalya', 'Muratpaşa', 'e_arsiv', 30, 'info@akdenizkimya.com.tr', '0242 555 66 77'],
            ['SAT003', 'Karadeniz Metal', '3333333333', 'Trabzon V.D.', 'Trabzon', 'Ortahisar', 'e_fatura', 60, 'satis@karadenizmetal.com.tr', '0462 111 22 33'],
            ['SAT004', 'Şahin Ofis Kırtasiye', '4444444444', 'Etimesgut V.D.', 'Ankara', 'Etimesgut', 'none', 15, 'siparis@sahinkirtasiye.com', '0312 555 88 99'],
        ];
        foreach ($supplierData as [$code, $name, $vkn, $office, $city, $district, $einv, $term, $email, $phone]) {
            $suppliers[] = Partner::updateOrCreate(
                ['tenant_id' => $tenant->id, 'partner_code' => $code],
                [
                    'name' => $name,
                    'entity_type' => 'company',
                    'tax_number' => $vkn,
                    'tax_office' => $office,
                    'e_invoice_status' => $einv,
                    'country' => 'Türkiye',
                    'city' => $city,
                    'district' => $district,
                    'email' => $email,
                    'phone' => $phone,
                    'is_customer' => false,
                    'is_supplier' => true,
                    'payment_term_days' => $term,
                    'account_code_payable' => '320.01.'.$code,
                    'currency_code' => 'TRY',
                    'is_active' => true,
                ],
            );
        }

        return [$customers, $suppliers];
    }

    /**
     * @return array<int, Product>
     */
    private function seedInventory(Tenant $tenant): array
    {
        // InventoryDefaultsService "Ana Depo" + "Stok" lokasyonu, UomCategory + Adet birimi kurar.
        $stockLocation = Location::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)->where('name', 'Stok')->firstOrFail();
        $uom = Uom::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)->orderBy('id')->firstOrFail();

        $catData = ['Ofis Malzemeleri', 'Ham Madde', 'Yardımcı Malzeme'];
        $categories = [];
        foreach ($catData as $ad) {
            $categories[$ad] = ProductCategory::updateOrCreate(
                ['tenant_id' => $tenant->id, 'name' => $ad],
            );
        }

        $productData = [
            ['A4 Fotokopi Kağıdı 80gr', 'ofis-a4-80gr', 'Ofis Malzemeleri', 60.00, 45.00, 500],
            ['Kartuş HP 305A Siyah', 'kartus-hp-305a', 'Ofis Malzemeleri', 850.00, 620.00, 40],
            ['Alüminyum Sac 2mm', 'aluminyum-sac-2mm', 'Ham Madde', 320.00, 250.00, 120],
            ['Sıvı Yapıştırıcı 500ml', 'sivi-yapistirici-500ml', 'Yardımcı Malzeme', 45.00, 30.00, 200],
            ['Endüstriyel Boya Kırmızı 5lt', 'boya-kirmizi-5lt', 'Yardımcı Malzeme', 480.00, 380.00, 80],
        ];

        $products = [];
        foreach ($productData as [$name, $sku, $catName, $salePrice, $cost, $initialStock]) {
            $product = Product::withoutGlobalScopes()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'sku' => $sku],
                [
                    'name' => $name,
                    'product_type' => 'stockable',
                    'uom_id' => $uom->id,
                    'product_category_id' => $categories[$catName]->id,
                    'list_price' => $salePrice,
                    'standard_cost' => $cost,
                    'cost_method' => 'standard',
                    'track_by' => 'none',
                    'reservation_method' => 'auto',
                    'is_kit' => false,
                    'sale_ok' => true,
                    'purchase_ok' => true,
                ],
            );
            StockQuant::withoutGlobalScopes()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'product_id' => $product->id, 'location_id' => $stockLocation->id, 'lot_id' => null],
                ['qty' => $initialStock, 'reserved_qty' => 0],
            );
            $products[] = $product;
        }

        return $products;
    }

    /**
     * @return array{admin: User, purchaser: User, salesRep: User}
     */
    private function seedActors(Tenant $tenant): array
    {
        $admin = User::where('tenant_id', $tenant->id)->whereHas('roles', fn ($q) => $q->where('name', 'Tenant Admin'))->firstOrFail();

        $purchaser = User::firstOrNew(['email' => 'satinalma@testfirma.local']);
        $purchaser->fill(['name' => 'Ayşe Satın Alma', 'password' => 'password']);
        $purchaser->tenant_id = $tenant->id;
        $purchaser->save();
        if (! $purchaser->hasRole('Purchasing Officer')) {
            $purchaser->assignRole('Purchasing Officer');
        }

        $salesRep = User::firstOrNew(['email' => 'satis@testfirma.local']);
        $salesRep->fill(['name' => 'Kerem Satış', 'password' => 'password']);
        $salesRep->tenant_id = $tenant->id;
        $salesRep->save();
        if (! $salesRep->hasRole('Sales Representative')) {
            $salesRep->assignRole('Sales Representative');
        }

        return ['admin' => $admin, 'purchaser' => $purchaser, 'salesRep' => $salesRep];
    }

    /**
     * @param  array<int, Partner>  $suppliers
     * @param  array<int, Product>  $products
     * @param  array{admin: User, purchaser: User, salesRep: User}  $actors
     */
    private function seedPurchaseFlow(Tenant $tenant, array $suppliers, array $products, array $actors): void
    {
        // Idempotency guard: her demo çalışmada aynı POları üretmemek için partner-supplier eşleşmesi kontrol
        if (PurchaseOrder::withoutGlobalScopes()->where('tenant_id', $tenant->id)->exists()) {
            return;
        }

        $stock = Location::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('name', 'Stok')->firstOrFail();
        $poSvc = app(PurchaseOrderService::class);
        $invSvc = app(InvoiceService::class);

        try {
            // 1) Taslak PO — Marmara Kağıt: 200 adet A4
            $draft = $poSvc->create($tenant->id, $suppliers[0]->id, $actors['purchaser']);
            $poSvc->addLine($draft, $products[0]->id, $products[0]->uom_id, '200', '42.0000');

            // 2) RFQ Gönderildi — Akdeniz Kimya: 30 yapıştırıcı + 20 boya
            $rfq = $poSvc->create($tenant->id, $suppliers[1]->id, $actors['purchaser']);
            $poSvc->addLine($rfq, $products[3]->id, $products[3]->uom_id, '30', '28.0000');
            $poSvc->addLine($rfq, $products[4]->id, $products[4]->uom_id, '20', '360.0000');
            $poSvc->sendRfq($rfq);

            // 3) Onaylandı + Kısmi Alım — Karadeniz Metal: 50 alüminyum, 30 teslim alındı
            $confirmedPartial = $poSvc->create($tenant->id, $suppliers[2]->id, $actors['purchaser']);
            $line3 = $poSvc->addLine($confirmedPartial, $products[2]->id, $products[2]->uom_id, '50', '240.0000');
            $poSvc->sendRfq($confirmedPartial);
            $poSvc->confirm($confirmedPartial->fresh(), $actors['admin']);
            $poSvc->receive($line3->fresh(), '30', $stock->id);

            // 4) Tamamlandı + Fatura Kesildi — Şahin Kırtasiye: 15 kartuş, hepsi alındı, PI post edildi
            $done = $poSvc->create($tenant->id, $suppliers[3]->id, $actors['purchaser']);
            $line4 = $poSvc->addLine($done, $products[1]->id, $products[1]->uom_id, '15', '600.0000');
            $poSvc->sendRfq($done);
            $poSvc->confirm($done->fresh(), $actors['admin']);
            $poSvc->receive($line4->fresh(), '15', $stock->id);

            $pi = $invSvc->create($tenant->id, $suppliers[3]->id, 'purchase', $done->fresh());
            $invSvc->addLine($pi, $products[1]->id, '15', '600.0000', null);
            $invSvc->post($pi->fresh(), $actors['admin']);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * @param  array<int, Partner>  $customers
     * @param  array<int, Product>  $products
     * @param  array{admin: User, purchaser: User, salesRep: User}  $actors
     */
    private function seedSalesFlow(Tenant $tenant, array $customers, array $products, array $actors): void
    {
        if (SalesOrder::withoutGlobalScopes()->where('tenant_id', $tenant->id)->exists()) {
            return;
        }

        $stock = Location::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('name', 'Stok')->firstOrFail();
        $soSvc = app(SalesOrderService::class);
        $invSvc = app(InvoiceService::class);

        try {
            // 1) Taslak Teklif — Toros İnşaat: 40 alüminyum sac
            $draftQuote = $soSvc->create($tenant->id, $customers[2]->id, $stock->id, $actors['salesRep']);
            $soSvc->addLine($draftQuote, $products[2]->id, $products[2]->uom_id, '40', '320.0000');

            // 2) Gönderilmiş Teklif — Anadolu Tekstil: 100 A4 + 5 kartuş
            $sentQuote = $soSvc->create($tenant->id, $customers[0]->id, $stock->id, $actors['salesRep']);
            $soSvc->addLine($sentQuote, $products[0]->id, $products[0]->uom_id, '100', '58.0000');
            $soSvc->addLine($sentQuote, $products[1]->id, $products[1]->uom_id, '5', '830.0000');
            $soSvc->sendQuotation($sentQuote, now()->addDays(30)->toDateString());

            // 3) Onaylandı + Kısmi Teslim — Ege Gıda: 50 yapıştırıcı, 20 teslim edildi
            $confirmedPartial = $soSvc->create($tenant->id, $customers[1]->id, $stock->id, $actors['salesRep']);
            $line3 = $soSvc->addLine($confirmedPartial, $products[3]->id, $products[3]->uom_id, '50', '44.0000');
            $soSvc->sendQuotation($confirmedPartial, now()->addDays(30)->toDateString());
            $soSvc->confirm($confirmedPartial->fresh(), $actors['admin']);
            $soSvc->deliver($line3->fresh(), '20');

            // 4) Tamamlandı + Satış Faturası — Mehmet Yılmaz (bireysel): 3 boya, hepsi teslim + SI kesildi
            $done = $soSvc->create($tenant->id, $customers[3]->id, $stock->id, $actors['salesRep']);
            $line4 = $soSvc->addLine($done, $products[4]->id, $products[4]->uom_id, '3', '475.0000');
            $soSvc->sendQuotation($done, now()->addDays(15)->toDateString());
            $soSvc->confirm($done->fresh(), $actors['admin']);
            $soSvc->deliver($line4->fresh(), '3');

            $si = $invSvc->create($tenant->id, $customers[3]->id, 'sales', $done->fresh());
            $invSvc->addLine($si, $products[4]->id, '3', '475.0000', null);
            $invSvc->post($si->fresh(), $actors['admin']);

            // Kısmi ödeme (nakit)
            $cashJournal = Journal::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('type', 'cash')->first();
            if ($cashJournal !== null) {
                $paySvc = app(PaymentService::class);
                $payment = $paySvc->create($tenant->id, $customers[3]->id, $cashJournal->id, '1000.0000', now()->toDateString());
                $paySvc->allocate($payment, $si->fresh(), '1000.0000');
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
