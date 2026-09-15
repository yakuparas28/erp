<?php

namespace Modules\Sales\Services;

use Illuminate\Support\Facades\DB;
use Modules\Sales\Models\DeliveryNote;
use Modules\Sales\Models\DeliveryNoteLine;
use Modules\Sales\Models\SalesOrderLine;

/**
 * İrsaliye üretimi. SalesOrderService::deliver() her satır teslim
 * için bir DeliveryNote (o an için tek-satırlık) yaratır. Manual
 * multi-line irsaliyeler için createFromLines() de sunuluyor.
 */
class DeliveryNoteService
{
    /**
     * Tek satırlık irsaliye — mevcut deliver() akışıyla uyumlu.
     * Stok hareketleri hâlâ SalesOrderService::deliver()'da; bu servis
     * sadece belge kaydını üretir.
     */
    public function recordDelivery(SalesOrderLine $line, string $qty, array $meta = []): DeliveryNote
    {
        return DB::transaction(function () use ($line, $qty, $meta) {
            $so = $line->salesOrder;
            $note = new DeliveryNote([
                'sales_order_id' => $so->id,
                'note_no' => $this->nextNoteNo($so->tenant_id),
                'delivery_date' => $meta['delivery_date'] ?? now()->toDateString(),
                'driver_name' => $meta['driver_name'] ?? null,
                'vehicle_plate' => $meta['vehicle_plate'] ?? null,
                'notes' => $meta['notes'] ?? null,
                'status' => DeliveryNote::STATUS_DELIVERED,
                'created_by' => auth()->id(),
            ]);
            $note->tenant_id = $so->tenant_id;
            $note->save();
            $noteLine = new DeliveryNoteLine([
                'delivery_note_id' => $note->id,
                'sales_order_line_id' => $line->id,
                'product_id' => $line->product_id,
                'uom_id' => $line->uom_id,
                'qty' => $qty,
            ]);
            $noteLine->tenant_id = $so->tenant_id;
            $noteLine->save();

            return $note;
        });
    }

    public function totalDeliveredForLine(int $lineId): string
    {
        return (string) (DeliveryNoteLine::where('sales_order_line_id', $lineId)
            ->whereHas('deliveryNote', fn ($q) => $q->where('status', '!=', DeliveryNote::STATUS_CANCELLED))
            ->sum('qty') ?? 0);
    }

    /**
     * Tenant içinde artan sıralı belge no: IRS-2026-000001
     */
    private function nextNoteNo(int $tenantId): string
    {
        $year = now()->format('Y');
        $last = DeliveryNote::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('note_no', 'like', "IRS-{$year}-%")
            ->orderByDesc('id')
            ->first();
        $next = 1;
        if ($last !== null) {
            $parts = explode('-', $last->note_no);
            $next = ((int) end($parts)) + 1;
        }

        return sprintf('IRS-%s-%06d', $year, $next);
    }
}
