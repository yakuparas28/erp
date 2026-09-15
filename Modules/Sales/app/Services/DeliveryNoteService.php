<?php

namespace Modules\Sales\Services;

use Illuminate\Support\Facades\DB;
use Modules\Sales\Models\DeliveryNote;
use Modules\Sales\Models\DeliveryNoteLine;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Models\SalesOrderLine;

/**
 * İrsaliye üretimi. İki mod:
 *  • recordDelivery(line, qty) — tek-satırlık şort yol (eski API,
 *    SalesOrderService::deliver()'ın default kullanımı).
 *  • createNoteHeader(so, meta) + recordDelivery(line, qty, [], $note)
 *    — çoklu satır tek irsaliye (yeni "İrsaliye Oluştur" akışı).
 * Stok hareketleri hâlâ SalesOrderService::deliver()'dadır; bu servis
 * yalnızca yasal belge kaydını üretir.
 */
class DeliveryNoteService
{
    public function recordDelivery(SalesOrderLine $line, string $qty, array $meta = [], ?DeliveryNote $existingNote = null): DeliveryNote
    {
        return DB::transaction(function () use ($line, $qty, $meta, $existingNote) {
            $so = $line->salesOrder;
            $note = $existingNote ?? $this->createNoteHeader($so, $meta);

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

    /**
     * Boş irsaliye başlığı yaratır. Çağıran, ardından her satırı
     * recordDelivery(line, qty, [], $note) ile bu belgeye ekler.
     * Aynı DB transaction'ı içinde çağrılmalı — kısmi başarı durumu
     * yasal belge tutarlılığını bozar.
     */
    public function createNoteHeader(SalesOrder $so, array $meta = []): DeliveryNote
    {
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

        return $note;
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
