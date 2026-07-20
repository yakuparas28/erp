<?php

namespace Modules\Purchase\Services;

use App\Models\User;
use Modules\Purchase\Models\PurchaseOrder;
use Modules\Purchase\Models\PurchaseOrderLine;

/**
 * Satınalma sipariş yaşam döngüsü (PRD 3.10): draft→rfq_sent→confirmed→
 * done/cancelled. Görev ayrılığı: oluşturan kullanıcı kendi PO'sunu
 * onaylayamaz (Inventory Adjustment ile aynı ilke).
 */
class PurchaseOrderService
{
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

        $po->update(['status' => 'confirmed']);
    }

    public function cancel(PurchaseOrder $po): void
    {
        abort_if(in_array($po->status, ['done', 'cancelled'], true), 422, __('This purchase order is already finalized.'));

        $po->update(['status' => 'cancelled']);
    }
}
