<?php

namespace Modules\Inventory\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Inventory\Models\InventoryAdjustment;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Services\SyncBulkPushService;
use Modules\Inventory\Services\SyncCursorService;

class SyncController extends Controller
{
    public function __construct(
        private readonly SyncCursorService $cursors,
        private readonly SyncBulkPushService $bulkPush,
    ) {}

    /**
     * Full Local Catalog + Delta Sync (PRD 3.2–3.3): ilk indirmede tüm
     * katalog, sonrasında yalnızca cursor'dan sonra değişen ürünler.
     */
    public function catalog(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $cursor = $this->cursors->decode($request->query('cursor'));

        $query = Product::with(['barcodes', 'uom'])
            ->where('tenant_id', $tenantId)
            ->orderBy('updated_at')
            ->orderBy('id');

        if ($cursor !== null) {
            $query->where(function ($q) use ($cursor): void {
                $q->where('updated_at', '>', $cursor['updated_at'])
                    ->orWhere(function ($q2) use ($cursor): void {
                        $q2->where('updated_at', '=', $cursor['updated_at'])
                            ->where('id', '>', $cursor['id']);
                    });
            });
        }

        $products = $query->get();
        $last = $products->last();

        $nextCursor = $last !== null
            ? $this->cursors->encode($last->updated_at->toDateTimeString(), $last->id)
            : ($cursor !== null ? $request->query('cursor') : $this->cursors->encode(now()->toDateTimeString(), 0));

        return response()->json([
            'products' => $products->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'uom_id' => $product->uom_id,
                'track_by' => $product->track_by,
                'barcodes' => $product->barcodes->map(fn ($barcode) => [
                    'barcode' => $barcode->barcode,
                    'uom_id' => $barcode->uom_id,
                ]),
            ]),
            'uoms' => Uom::where('tenant_id', $tenantId)->get(['id', 'uom_category_id', 'name', 'factor', 'is_reference']),
            'next_cursor' => $nextCursor,
        ]);
    }

    /**
     * Bulk Push (PRD 3.2–3.3): en fazla 1000 satırlık bir sayım paketi,
     * kendi batch_uuid'siyle gönderilir; sync_batches ile idempotent yazılır.
     */
    public function pushCounts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'batch_uuid' => ['required', 'uuid'],
            'inventory_adjustment_id' => ['required', 'integer'],
            'lines' => ['required', 'array', 'max:1000'],
            'lines.*.barcode' => ['required', 'string'],
            'lines.*.qty' => ['required', 'numeric'],
            'lines.*.lot_id' => ['nullable', 'integer'],
        ]);

        $tenantId = $request->user()->tenant_id;

        $adjustment = InventoryAdjustment::where('tenant_id', $tenantId)
            ->findOrFail($validated['inventory_adjustment_id']);

        $this->bulkPush->push($tenantId, $validated['batch_uuid'], $adjustment, $validated['lines']);

        return response()->json(['status' => 'ok']);
    }
}
