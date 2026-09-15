<?php

namespace Modules\Purchase\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Approval\ApprovalService;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Services\CostingService;
use Modules\Inventory\Services\PutawayService;
use Modules\Inventory\Services\StockMoveService;
use Modules\Purchase\Events\PurchaseOrderLineReceived;
use Modules\Purchase\Models\GoodsReceipt;
use Modules\Purchase\Models\PurchaseOrder;
use Modules\Purchase\Models\PurchaseOrderLine;

/**
 * Satınalma sipariş yaşam döngüsü (PRD 3.10): draft→rfq_sent→confirmed→
 * done/cancelled. Görev ayrılığı: oluşturan kullanıcı kendi PO'sunu
 * onaylayamaz (Inventory Adjustment ile aynı ilke). "Beklenen teslimat"
 * ayrı bir stock_move ile temsil edilmez (defter yalnızca gerçek
 * hareketleri tutar); receive() gerçek teslim alımda çağrılır.
 */
class PurchaseOrderService
{
    public function __construct(
        private readonly StockMoveService $stockMoves,
        private readonly CostingService $costing,
        private readonly PutawayService $putaway,
        private readonly GoodsReceiptService $goodsReceipts,
        private readonly ApprovalService $approvals,
    ) {}

    public function subtotal(PurchaseOrder $po): string
    {
        return $po->lines->reduce(
            fn (string $carry, PurchaseOrderLine $line) => bcadd($carry, bcmul((string) $line->qty, (string) $line->unit_price, 4), 4),
            '0.0000',
        );
    }

    public function requiresConfirmationApproval(PurchaseOrder $po): bool
    {
        $tenant = Tenant::find($po->tenant_id);
        $threshold = $tenant?->purchase_order_approval_threshold;

        if ($threshold === null || bccomp((string) $threshold, '0', 4) <= 0) {
            return false;
        }

        return bccomp($this->subtotal($po), (string) $threshold, 4) >= 0;
    }

    public function submitForConfirmationApproval(PurchaseOrder $po, User $submitter): void
    {
        abort_unless($po->status === 'rfq_sent', 422, __('Only RFQ-sent purchase orders can be submitted for approval.'));
        abort_unless($this->requiresConfirmationApproval($po), 422, __('This purchase order does not require approval.'));
        abort_if($po->isPendingApprovalFor('purchase_order'), 422, __('An approval request is already pending for this purchase order.'));

        $this->approvals->submit($po, $submitter, 'purchase_order');
    }

    public function create(int $tenantId, int $partnerId, User $creator, string $billControlPolicy = 'received_qty'): PurchaseOrder
    {
        $po = new PurchaseOrder([
            'partner_id' => $partnerId,
            'created_by' => $creator->id,
            'bill_control_policy' => $billControlPolicy,
            'status' => 'draft',
        ]);
        $po->tenant_id = $tenantId;
        $po->save();

        return $po;
    }

    public function addLine(PurchaseOrder $po, int $productId, int $uomId, string $qty, string $unitPrice): PurchaseOrderLine
    {
        abort_unless($po->status === 'draft', 422, __('Lines can only be added to a draft purchase order.'));

        $product = Product::withoutGlobalScopes()->findOrFail($productId);
        $uom = Uom::withoutGlobalScopes()->findOrFail($uomId);

        abort_if(
            $uom->uom_category_id !== $product->uom->uom_category_id,
            422,
            __('The selected unit does not belong to this product\'s unit category.'),
        );

        $line = new PurchaseOrderLine([
            'purchase_order_id' => $po->id,
            'product_id' => $productId,
            'uom_id' => $uomId,
            'qty' => $qty,
            'unit_price' => $unitPrice,
        ]);
        $line->tenant_id = $po->tenant_id;
        $line->save();

        return $line;
    }

    public function sendRfq(PurchaseOrder $po): void
    {
        abort_unless($po->status === 'draft', 422, __('Only draft purchase orders can be sent as an RFQ.'));

        $po->update(['status' => 'rfq_sent']);
    }

    public function confirm(PurchaseOrder $po, User $approver): void
    {
        abort_unless($po->status === 'rfq_sent', 422, __('Only an RFQ-sent purchase order can be confirmed.'));
        abort_if($po->created_by === $approver->id, 403, __('You cannot confirm a purchase order you created.'));
        abort_unless($approver->can('confirm purchase orders'), 403, __('You are not allowed to confirm purchase orders.'));

        if ($this->requiresConfirmationApproval($po) && ! $po->fresh()->isApprovedFor('purchase_order')) {
            abort(422, __('This purchase order exceeds the approval threshold and must be approved before it can be confirmed.'));
        }

        $po->update(['status' => 'confirmed']);
    }

    /**
     * Fiili mal kabul (PRD 3.10): gerçek stock_move (satıra referansla) +
     * maliyet katmanı (satırın unit_price'ı ile) üretir. Putaway kuralı
     * farklı bir hedef önerirse ek bir transfer yapılır — transferler
     * değer yaratmadığından maliyet yalnızca ilk (alım) hareketinde
     * kaydedilir (Faz 5 kararıyla tutarlı).
     */
    public function receive(PurchaseOrderLine $line, string $qty, int $receivingLocationId, ?int $lotId = null, ?GoodsReceipt $receipt = null): void
    {
        $po = $line->purchaseOrder;

        abort_unless($po->status === 'confirmed', 422, __('Only a confirmed purchase order can be received.'));

        $product = Product::withoutGlobalScopes()->findOrFail($line->product_id);
        $uom = $line->uom;

        $finalLocation = Location::withoutGlobalScopes()->findOrFail($receivingLocationId);
        $warehouse = $finalLocation->warehouse_id
            ? Warehouse::withoutGlobalScopes()->find($finalLocation->warehouse_id)
            : null;

        $steps = $warehouse?->reception_steps ?? 'one_step';

        // Odoo multi-step reception: 1-step: direkt final; 2-step: input->final;
        // 3-step: input->QC->final. Costing yalnızca ilk (mal kabul) hareketinde.
        [$firstInboundTo, $intermediateChain] = match ($steps) {
            'two_step' => $warehouse->input_location_id
                ? [$warehouse->input_location_id, [$receivingLocationId]]
                : [$receivingLocationId, []],
            'three_step' => ($warehouse->input_location_id && $warehouse->quality_location_id)
                ? [$warehouse->input_location_id, [$warehouse->quality_location_id, $receivingLocationId]]
                : [$receivingLocationId, []],
            default => [$receivingLocationId, []],
        };

        $firstMove = $this->stockMoves->move(
            tenantId: $po->tenant_id,
            product: $product,
            fromLocationId: null,
            toLocationId: $firstInboundTo,
            qty: $qty,
            uom: $uom,
            referenceType: 'purchase_order_line',
            referenceId: $line->id,
            lotId: $lotId,
        );

        $this->costing->recordInbound($product, $firstMove, $firstMove->qty, $line->unit_price);

        $currentLocation = $firstInboundTo;
        foreach ($intermediateChain as $nextLocation) {
            $this->stockMoves->move(
                tenantId: $po->tenant_id,
                product: $product,
                fromLocationId: $currentLocation,
                toLocationId: $nextLocation,
                qty: bcmul($firstMove->qty, '-1', 4),
                uom: $product->uom,
                referenceType: 'purchase_order_line',
                referenceId: $line->id,
                lotId: $lotId,
            );
            $this->stockMoves->move(
                tenantId: $po->tenant_id,
                product: $product,
                fromLocationId: $currentLocation,
                toLocationId: $nextLocation,
                qty: $firstMove->qty,
                uom: $product->uom,
                referenceType: 'purchase_order_line',
                referenceId: $line->id,
                lotId: $lotId,
            );
            $currentLocation = $nextLocation;
        }

        // Putaway sadece 1-step'te uygulanır — multi-step zaten hedefe zincirle taşıdı.
        if ($steps === 'one_step') {
            $destinationId = $this->putaway->resolveDestination($product, $receivingLocationId);

            if ($destinationId !== null && $destinationId !== $receivingLocationId) {
                $this->stockMoves->move(
                    tenantId: $po->tenant_id,
                    product: $product,
                    fromLocationId: $receivingLocationId,
                    toLocationId: $destinationId,
                    qty: bcmul($firstMove->qty, '-1', 4),
                    uom: $product->uom,
                    referenceType: 'purchase_order_line',
                    referenceId: $line->id,
                    lotId: $lotId,
                );

                $this->stockMoves->move(
                    tenantId: $po->tenant_id,
                    product: $product,
                    fromLocationId: $receivingLocationId,
                    toLocationId: $destinationId,
                    qty: $firstMove->qty,
                    uom: $product->uom,
                    referenceType: 'purchase_order_line',
                    referenceId: $line->id,
                    lotId: $lotId,
                );
            }
        }

        $this->goodsReceipts->recordReceipt($line, $qty, $receivingLocationId, [], $receipt);

        PurchaseOrderLineReceived::dispatch($line, $firstMove);
    }

    /**
     * Çoklu satırı tek mal kabul fişine toplayan akış. Transaction
     * bütünlüğü — kısmi başarı yok, hiçbiri veya hepsi.
     *
     * @param  array<int, array{line_id:int, qty:string}>  $items
     * @param  array{receipt_date?:?string, waybill_no?:?string, notes?:?string}  $meta
     */
    public function receiveMany(PurchaseOrder $po, array $items, int $receivingLocationId, array $meta = []): GoodsReceipt
    {
        abort_unless($po->status === 'confirmed', 422, __('Only a confirmed purchase order can be received.'));
        abort_if($items === [], 422, __('Select at least one line to include on the goods receipt.'));

        return DB::transaction(function () use ($po, $items, $receivingLocationId, $meta) {
            $receipt = $this->goodsReceipts->createReceiptHeader($po, $receivingLocationId, $meta);

            foreach ($items as $item) {
                $line = PurchaseOrderLine::withoutGlobalScopes()->findOrFail($item['line_id']);
                abort_if($line->purchase_order_id !== $po->id, 422, __('One of the selected lines does not belong to this order.'));
                $this->receive($line, (string) $item['qty'], $receivingLocationId, null, $receipt);
            }

            return $receipt->fresh();
        });
    }

    /**
     * Odoo `stock.return.picking` denkliği: teslim alınmış bir kalemi
     * tedarikçiye geri gönderir. Ters yönde stock_move üretir; kaynak
     * ürün kabul edilmiş lokasyondan çıkar. Maliyet katmanı yeni bir
     * çıkış olarak `consumeOutbound` ile tüketilir.
     */
    public function returnReceipt(PurchaseOrderLine $line, string $qty, int $fromLocationId, ?int $lotId = null): void
    {
        abort_if(bccomp($qty, '0', 4) <= 0, 422, __('Return quantity must be positive.'));

        $received = $line->receivedQty();
        abort_if(bccomp($qty, $received, 4) > 0, 422, __('Return quantity cannot exceed the received quantity.'));

        $product = Product::withoutGlobalScopes()->findOrFail($line->product_id);
        $uom = $line->uom;

        $move = $this->stockMoves->move(
            tenantId: $line->tenant_id,
            product: $product,
            fromLocationId: $fromLocationId,
            toLocationId: null,
            qty: '-'.$qty,
            uom: $uom,
            referenceType: 'purchase_order_line_return',
            referenceId: $line->id,
            lotId: $lotId,
        );

        $this->costing->consumeOutbound($product, $move, bcmul($move->qty, '-1', 4));
    }

    public function cancel(PurchaseOrder $po): void
    {
        abort_if(in_array($po->status, ['done', 'cancelled'], true), 422, __('This purchase order is already finalized.'));

        $po->update(['status' => 'cancelled']);
    }
}
