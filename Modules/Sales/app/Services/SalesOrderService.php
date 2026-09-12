<?php

namespace Modules\Sales\Services;

use App\Mail\TemplatedMail;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Mail\NotificationTemplateService;
use App\Services\Mail\TenantMailer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\StockQuant;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Services\CostingService;
use Modules\Inventory\Services\KitExplosionService;
use Modules\Inventory\Services\RouteService;
use Modules\Inventory\Services\StockMoveService;
use Modules\Sales\Events\SalesOrderLineDelivered;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Models\SalesOrderLine;

/**
 * Satış sipariş yaşam döngüsü (PRD 3.11): draft→quotation_sent→confirmed→
 * done/cancelled. Görev ayrılığı: oluşturan kullanıcı kendi SO'sunu
 * onaylayamaz. Onayda rezervasyon yalnızca track_by='none' + is_kit=false +
 * product_type≠'service' satırlarda uygulanır (bkz. plan Architecture notu).
 */
class SalesOrderService
{
    public function __construct(
        private readonly StockMoveService $stockMoves,
        private readonly CostingService $costing,
        private readonly KitExplosionService $kitExplosion,
        private readonly RouteService $routes,
    ) {}

    public function create(int $tenantId, int $partnerId, int $locationId, User $creator): SalesOrder
    {
        $so = new SalesOrder([
            'partner_id' => $partnerId,
            'location_id' => $locationId,
            'created_by' => $creator->id,
            'status' => 'draft',
        ]);
        $so->tenant_id = $tenantId;
        $so->save();

        return $so;
    }

    /**
     * @param  array<int, string>|null  $customValues  attribute_value_id → serbest metin
     */
    public function addLine(SalesOrder $so, int $productId, int $uomId, string $qty, string $unitPrice, ?array $customValues = null): SalesOrderLine
    {
        abort_unless($so->status === 'draft', 422, __('Lines can only be added to a draft sales order.'));

        $product = Product::withoutGlobalScopes()->findOrFail($productId);
        $uom = Uom::withoutGlobalScopes()->findOrFail($uomId);

        abort_if(
            $uom->uom_category_id !== $product->uom->uom_category_id,
            422,
            __('The selected unit does not belong to this product\'s unit category.'),
        );

        $line = new SalesOrderLine([
            'sales_order_id' => $so->id,
            'product_id' => $productId,
            'uom_id' => $uomId,
            'qty' => $qty,
            'unit_price' => $unitPrice,
            'custom_values' => $customValues !== null && $customValues !== [] ? $customValues : null,
        ]);
        $line->tenant_id = $so->tenant_id;
        $line->save();

        return $line;
    }

    public function sendQuotation(SalesOrder $so, ?string $validityDate = null): void
    {
        abort_unless($so->status === 'draft', 422, __('Only draft sales orders can be sent as a quotation.'));

        $so->update([
            'status' => 'quotation_sent',
            'sent_at' => now(),
            'validity_date' => $validityDate ?: $so->validity_date,
            'access_token' => $so->access_token ?: Str::random(48),
        ]);

        activity()
            ->causedBy(auth()->user())
            ->performedOn($so)
            ->withProperties([
                'validity_date' => $so->validity_date?->toDateString(),
            ])
            ->log('sales_order.quotation_sent');

        $this->emailQuotation($so);
    }

    private function emailQuotation(SalesOrder $so): void
    {
        $partner = $so->partner;

        if ($partner === null || empty($partner->email)) {
            return;
        }

        $tenant = Tenant::withoutGlobalScopes()->find($so->tenant_id);

        $total = $so->lines->reduce(
            fn (string $carry, $line) => bcadd($carry, bcmul((string) $line->qty, (string) $line->unit_price, 4), 4),
            '0.0000',
        );

        try {
            $rendered = app(NotificationTemplateService::class)->render('sales_order_quotation', $so->tenant_id, [
                'firma_adi' => $tenant?->name ?? '',
                'musteri_adi' => $partner->name,
                'teklif_no' => 'SO-'.str_pad((string) $so->id, 5, '0', STR_PAD_LEFT),
                'toplam' => $total,
                'gecerlilik_tarihi' => $so->validity_date?->format('d.m.Y') ?? __('Not specified'),
                'teklif_baglantisi' => route('portal.quote', ['token' => $so->access_token]),
            ]);

            app(TenantMailer::class)->send(
                $so->tenant_id,
                $partner->email,
                new TemplatedMail($rendered['subject'], $rendered['body']),
            );
        } catch (\Throwable $e) {
            // Mail gönderilemezse teklif akışı bloke olmasın; activity log'da hata izlenebilir
            activity()
                ->causedBy(auth()->user())
                ->performedOn($so)
                ->withProperties(['error' => $e->getMessage()])
                ->log('sales_order.quotation_email_failed');
        }
    }

    public function confirm(SalesOrder $so, ?User $approver = null, bool $byCustomer = false): void
    {
        abort_unless($so->status === 'quotation_sent', 422, __('Only a quotation-sent sales order can be confirmed.'));

        if (! $byCustomer) {
            abort_if($approver === null, 403, __('You are not allowed to confirm sales orders.'));
            abort_if($so->created_by === $approver->id, 403, __('You cannot confirm a sales order you created.'));
            abort_unless($approver->can('confirm sales orders'), 403, __('You are not allowed to confirm sales orders.'));
        }

        DB::transaction(function () use ($so, $byCustomer): void {
            foreach ($so->lines as $line) {
                $product = Product::withoutGlobalScopes()->findOrFail($line->product_id);
                if ($product->reservation_method === 'at_confirmation') {
                    $this->reserveLine($so, $line);
                }
            }

            // Odoo `sale.order.route_id` denkliği: pull rota tanımlanmışsa
            // her satır için pull zincirini çalıştır.
            if ($so->route_id !== null) {
                $route = $so->inventoryRoute()->firstOrFail();
                foreach ($so->lines as $line) {
                    $product = Product::withoutGlobalScopes()->findOrFail($line->product_id);
                    if ($product->product_type === 'service' || $product->is_kit) {
                        continue;
                    }
                    $this->routes->executePull($route, $product, (string) $line->qty, $line->uom);
                }
            }

            $so->update([
                'status' => 'confirmed',
                'customer_confirmed_at' => $byCustomer ? now() : $so->customer_confirmed_at,
            ]);
        });
    }

    public function declineByCustomer(SalesOrder $so): void
    {
        abort_unless($so->status === 'quotation_sent', 422, __('This quotation cannot be declined anymore.'));

        $so->update([
            'status' => 'cancelled',
            'customer_declined_at' => now(),
        ]);
    }

    /**
     * Odoo `stock.move.action_assign` denkliği: manual reservation modundaki
     * bir SO satırını elle rezerv eder. Sadece confirmed SO'da, kalan miktar
     * kadar rezerv edilebilir.
     */
    public function reserveManually(SalesOrderLine $line, string $qty): void
    {
        $so = $line->salesOrder;

        abort_unless($so->status === 'confirmed', 422, __('Only confirmed sales orders can be reserved.'));
        abort_if(bccomp($qty, '0', 4) <= 0, 422, __('Reserve quantity must be positive.'));

        $product = Product::withoutGlobalScopes()->findOrFail($line->product_id);
        abort_unless($this->isReservable($product), 422, __('This product cannot be reserved.'));

        $remaining = bcsub($line->qty, bcadd((string) $line->reserved_qty, (string) $line->delivered_qty, 4), 4);
        abort_if(bccomp($qty, $remaining, 4) > 0, 422, __('Reserve quantity cannot exceed the unreserved balance.'));

        DB::transaction(function () use ($so, $line, $qty): void {
            $this->addReservationQty($so, $line, $qty);
            $line->increment('reserved_qty', $qty);
        });
    }

    public function unreserveManually(SalesOrderLine $line): void
    {
        $so = $line->salesOrder;

        abort_unless($so->status === 'confirmed', 422, __('Only confirmed sales orders can be unreserved.'));
        abort_if(bccomp((string) $line->reserved_qty, '0', 4) <= 0, 422, __('This line has nothing reserved.'));

        DB::transaction(function () use ($so, $line): void {
            $this->releaseReservation($so, $line, (string) $line->reserved_qty);
            $line->update(['reserved_qty' => '0']);
        });
    }

    private function addReservationQty(SalesOrder $so, SalesOrderLine $line, string $qty): void
    {
        $product = Product::withoutGlobalScopes()->findOrFail($line->product_id);

        $quant = StockQuant::withoutGlobalScopes()
            ->where('tenant_id', $so->tenant_id)
            ->where('product_id', $product->id)
            ->where('location_id', $so->location_id)
            ->whereNull('lot_id')
            ->lockForUpdate()
            ->first();

        if ($quant === null) {
            $quant = new StockQuant([
                'product_id' => $product->id,
                'location_id' => $so->location_id,
                'lot_id' => null,
                'qty' => '0',
            ]);
            $quant->tenant_id = $so->tenant_id;
            $quant->save();
        }

        $newReserved = bcadd((string) $quant->reserved_qty, $qty, 4);

        abort_if(bccomp($newReserved, (string) $quant->qty, 4) > 0, 422, __('Insufficient available stock to reserve.'));

        $quant->update(['reserved_qty' => $newReserved]);
    }

    public function cancel(SalesOrder $so): void
    {
        abort_if(in_array($so->status, ['done', 'cancelled'], true), 422, __('This sales order is already finalized.'));

        DB::transaction(function () use ($so): void {
            if ($so->status === 'confirmed') {
                foreach ($so->lines as $line) {
                    $remaining = bcsub($line->qty, $line->delivered_qty, 4);

                    if (bccomp($remaining, '0', 4) > 0) {
                        $this->releaseReservation($so, $line, $remaining);
                    }
                }
            }

            $so->update(['status' => 'cancelled']);
        });
    }

    /**
     * Fiili teslimat (PRD 3.11): hizmet satırı hiçbir şey üretmez; kit
     * satırı bileşenlere patlar (kit'in kendisi asla move'a girmez);
     * normal satır tek bir çıkış hareketi + COGS üretir ve (rezerve
     * edilmişse) rezervi serbest bırakır. Tüm satırlar tam teslim
     * edilince SO 'done' durumuna geçer.
     */
    public function deliver(SalesOrderLine $line, string $qty): void
    {
        $so = $line->salesOrder;

        abort_unless($so->status === 'confirmed', 422, __('Only a confirmed sales order can be delivered.'));

        $remaining = bcsub($line->qty, $line->delivered_qty ?? '0', 4);
        abort_if(bccomp($qty, $remaining, 4) > 0, 422, __('Delivered quantity cannot exceed the remaining ordered quantity.'));

        $product = Product::withoutGlobalScopes()->findOrFail($line->product_id);

        DB::transaction(function () use ($so, $line, $qty, $product): void {
            if ($product->product_type === 'service') {
                $this->increaseDeliveredQty($so, $line, $qty);

                return;
            }

            if ($product->is_kit) {
                $moves = $this->kitExplosion->explode(
                    kit: $product,
                    kitQty: $qty,
                    fromLocationId: $so->location_id,
                    toLocationId: null,
                    referenceType: 'sales_order_line',
                    referenceId: $line->id,
                );

                foreach ($moves as $move) {
                    $moveProduct = Product::withoutGlobalScopes()->findOrFail($move->product_id);
                    $cogs = $this->costing->consumeOutbound($moveProduct, $move, bcmul($move->qty, '-1', 4));
                    SalesOrderLineDelivered::dispatch($line, $move, $cogs);
                }

                $this->increaseDeliveredQty($so, $line, $qty);

                return;
            }

            if ($this->isReservable($product)) {
                $this->releaseReservation($so, $line, $qty);
            }

            // Odoo multi-step delivery: 1-step: direkt müşteriye; 2-step:
            // source -> output -> müşteri; 3-step: source -> pack -> output -> müşteri.
            $sourceLocation = Location::withoutGlobalScopes()->findOrFail($so->location_id);
            $warehouse = $sourceLocation->warehouse_id
                ? Warehouse::withoutGlobalScopes()->find($sourceLocation->warehouse_id)
                : null;
            $steps = $warehouse?->delivery_steps ?? 'one_step';

            $deliveryChain = match ($steps) {
                'two_step' => $warehouse->output_location_id ? [$warehouse->output_location_id] : [],
                'three_step' => ($warehouse->pack_location_id && $warehouse->output_location_id)
                    ? [$warehouse->pack_location_id, $warehouse->output_location_id]
                    : [],
                default => [],
            };

            $currentLocation = $so->location_id;
            foreach ($deliveryChain as $nextLocation) {
                $this->stockMoves->move(
                    tenantId: $so->tenant_id,
                    product: $product,
                    fromLocationId: $currentLocation,
                    toLocationId: $nextLocation,
                    qty: bcmul($qty, '-1', 4),
                    uom: $line->uom,
                    referenceType: 'sales_order_line',
                    referenceId: $line->id,
                );
                $this->stockMoves->move(
                    tenantId: $so->tenant_id,
                    product: $product,
                    fromLocationId: $currentLocation,
                    toLocationId: $nextLocation,
                    qty: $qty,
                    uom: $line->uom,
                    referenceType: 'sales_order_line',
                    referenceId: $line->id,
                );
                $currentLocation = $nextLocation;
            }

            $move = $this->stockMoves->move(
                tenantId: $so->tenant_id,
                product: $product,
                fromLocationId: $currentLocation,
                toLocationId: null,
                qty: bcmul($qty, '-1', 4),
                uom: $line->uom,
                referenceType: 'sales_order_line',
                referenceId: $line->id,
            );

            $cogs = $this->costing->consumeOutbound($product, $move, $qty);
            SalesOrderLineDelivered::dispatch($line, $move, $cogs);

            $this->increaseDeliveredQty($so, $line, $qty);
        });
    }

    /**
     * Odoo `stock.return.picking` denkliği: müşteriye teslim edilen bir
     * kalemi geri alır. Ters yönde stock_move üretir; geri gelen ürün
     * verilen lokasyona döner. Costing yönü geriye çevrilmez (Odoo
     * varsayılan davranışıyla tutarlı — iade cari maliyetle stoklanır).
     */
    public function returnDelivery(SalesOrderLine $line, string $qty, int $returnLocationId): void
    {
        abort_if(bccomp($qty, '0', 4) <= 0, 422, __('Return quantity must be positive.'));
        abort_if(bccomp($qty, (string) $line->delivered_qty, 4) > 0, 422, __('Return quantity cannot exceed the delivered quantity.'));

        $so = $line->salesOrder;
        $product = Product::withoutGlobalScopes()->findOrFail($line->product_id);

        DB::transaction(function () use ($so, $line, $qty, $product, $returnLocationId): void {
            $this->stockMoves->move(
                tenantId: $so->tenant_id,
                product: $product,
                fromLocationId: null,
                toLocationId: $returnLocationId,
                qty: $qty,
                uom: $line->uom,
                referenceType: 'sales_order_line_return',
                referenceId: $line->id,
            );

            $line->decrement('delivered_qty', $qty);

            if ($so->status === 'done') {
                $so->update(['status' => 'confirmed']);
            }
        });
    }

    private function increaseDeliveredQty(SalesOrder $so, SalesOrderLine $line, string $qty): void
    {
        $line->increment('delivered_qty', $qty);
        $this->markDoneIfFullyDelivered($so);
    }

    private function markDoneIfFullyDelivered(SalesOrder $so): void
    {
        $so->refresh();

        $fullyDelivered = $so->lines->every(fn (SalesOrderLine $line) => bccomp($line->fresh()->delivered_qty, $line->qty, 4) === 0);

        if ($fullyDelivered) {
            $so->update(['status' => 'done']);
        }
    }

    private function reserveLine(SalesOrder $so, SalesOrderLine $line): void
    {
        $product = Product::withoutGlobalScopes()->findOrFail($line->product_id);

        if (! $this->isReservable($product)) {
            return;
        }

        $this->addReservationQty($so, $line, (string) $line->qty);
        $line->update(['reserved_qty' => $line->qty]);
    }

    private function releaseReservation(SalesOrder $so, SalesOrderLine $line, string $qty): void
    {
        $product = Product::withoutGlobalScopes()->findOrFail($line->product_id);

        if (! $this->isReservable($product)) {
            return;
        }

        StockQuant::withoutGlobalScopes()
            ->where('tenant_id', $so->tenant_id)
            ->where('product_id', $product->id)
            ->where('location_id', $so->location_id)
            ->whereNull('lot_id')
            ->decrement('reserved_qty', $qty);
    }

    private function isReservable(Product $product): bool
    {
        return $product->track_by === 'none' && ! $product->is_kit && $product->product_type !== 'service';
    }
}
