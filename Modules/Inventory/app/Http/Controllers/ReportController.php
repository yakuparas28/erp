<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\StockMove;
use Modules\Inventory\Models\StockQuant;
use Modules\Inventory\Models\StockValuationLayer;
use Modules\Inventory\Models\Warehouse;
use Modules\Purchase\Models\PurchaseOrderLine;
use Modules\Sales\Models\SalesOrderLine;

/**
 * Odoo `Inventory → Reporting` denkliği: salt-okunur analitik ekranlar
 * (Moves History, Valuation, Locations, Forecasted, Warehouse Analysis).
 * Sadece görselleştirir; state değiştirmez.
 */
class ReportController extends Controller
{
    public function moves(Request $request): View
    {
        $query = StockMove::with(['product', 'fromLocation', 'toLocation']);

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->integer('product_id'));
        }

        if ($request->filled('location_id')) {
            $locationId = $request->integer('location_id');
            $query->where(fn ($q) => $q->where('from_location_id', $locationId)->orWhere('to_location_id', $locationId));
        }

        if ($request->filled('reference_type')) {
            $query->where('reference_type', $request->string('reference_type'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date('date_to'));
        }

        return view('inventory::reports.moves', [
            'moves' => $query->orderByDesc('created_at')->limit(500)->get(),
            'products' => Product::orderBy('name')->get(),
            'locations' => Location::orderBy('name')->get(),
            'referenceTypes' => StockMove::query()->distinct()->pluck('reference_type'),
            'filters' => $request->only(['product_id', 'location_id', 'reference_type', 'date_from', 'date_to']),
        ]);
    }

    public function valuation(): View
    {
        $aggregates = StockValuationLayer::selectRaw('product_id, SUM(qty) as qty, SUM(remaining_value) as value')
            ->groupBy('product_id')
            ->havingRaw('SUM(remaining_value) > 0')
            ->get();

        $productMap = Product::with('category')->whereIn('id', $aggregates->pluck('product_id'))->get()->keyBy('id');

        $rows = $aggregates->map(function ($row) use ($productMap): object {
            $qty = (string) $row->qty;
            $value = (string) $row->value;
            $unitCost = bccomp($qty, '0', 4) > 0 ? bcdiv($value, $qty, 4) : '0';

            return (object) [
                'product' => $productMap[$row->product_id] ?? null,
                'qty' => $qty,
                'value' => $value,
                'unit_cost' => $unitCost,
            ];
        })->filter(fn ($r) => $r->product !== null);

        $grouped = $rows->groupBy(fn ($r) => $r->product?->category?->name ?? __('Uncategorized'));

        $totalValue = $rows->reduce(fn (string $carry, $r) => bcadd($carry, $r->value, 4), '0');

        return view('inventory::reports.valuation', [
            'grouped' => $grouped,
            'totalValue' => $totalValue,
        ]);
    }

    public function locations(): View
    {
        $rows = StockQuant::selectRaw('location_id, product_id, SUM(qty) as qty')
            ->groupBy('location_id', 'product_id')
            ->having(DB::raw('SUM(qty)'), '>', 0)
            ->get();

        $productMap = Product::whereIn('id', $rows->pluck('product_id')->unique())->get()->keyBy('id');
        $locationMap = Location::orderBy('name')->get()->keyBy('id');

        $byLocation = $rows->groupBy('location_id')->map(function ($items, $locationId) use ($productMap, $locationMap): object {
            return (object) [
                'location' => $locationMap[$locationId] ?? null,
                'total_qty' => $items->reduce(fn (string $c, $r) => bcadd($c, (string) $r->qty, 4), '0'),
                'products' => $items->map(fn ($r) => (object) [
                    'product' => $productMap[$r->product_id] ?? null,
                    'qty' => (string) $r->qty,
                ]),
            ];
        })->filter(fn ($row) => $row->location !== null)->sortBy(fn ($row) => $row->location->name)->values();

        return view('inventory::reports.locations', [
            'rows' => $byLocation,
        ]);
    }

    public function forecasted(): View
    {
        $currentStock = StockQuant::selectRaw('product_id, SUM(qty) as total')
            ->groupBy('product_id')
            ->pluck('total', 'product_id');

        $incoming = PurchaseOrderLine::selectRaw('product_id, SUM(qty) as ordered, SUM(COALESCE((SELECT SUM(qty) FROM stock_moves WHERE stock_moves.reference_type = "purchase_order_line" AND stock_moves.reference_id = purchase_order_lines.id AND qty > 0), 0)) as received')
            ->whereHas('purchaseOrder', fn ($q) => $q->whereIn('status', ['confirmed', 'done']))
            ->groupBy('product_id')
            ->get()
            ->keyBy('product_id');

        $outgoing = SalesOrderLine::selectRaw('product_id, SUM(qty) as ordered, SUM(delivered_qty) as delivered')
            ->whereHas('salesOrder', fn ($q) => $q->whereIn('status', ['confirmed', 'done']))
            ->groupBy('product_id')
            ->get()
            ->keyBy('product_id');

        $productIds = $currentStock->keys()
            ->merge($incoming->keys())
            ->merge($outgoing->keys())
            ->unique();

        $rows = Product::whereIn('id', $productIds)->orderBy('name')->get()->map(function ($product) use ($currentStock, $incoming, $outgoing): object {
            $onHand = (string) ($currentStock[$product->id] ?? '0');
            $incomingRow = $incoming[$product->id] ?? null;
            $outgoingRow = $outgoing[$product->id] ?? null;

            $pendingIn = $incomingRow
                ? bcsub((string) $incomingRow->ordered, (string) $incomingRow->received, 4)
                : '0';

            $pendingOut = $outgoingRow
                ? bcsub((string) $outgoingRow->ordered, (string) $outgoingRow->delivered, 4)
                : '0';

            $forecast = bcadd(bcsub($onHand, $pendingOut, 4), $pendingIn, 4);

            return (object) [
                'product' => $product,
                'on_hand' => $onHand,
                'incoming' => $pendingIn,
                'outgoing' => $pendingOut,
                'forecast' => $forecast,
            ];
        });

        return view('inventory::reports.forecasted', [
            'rows' => $rows,
        ]);
    }

    public function warehouseAnalysis(): View
    {
        $warehouses = Warehouse::with('locations')->get();

        $stats = $warehouses->map(function ($warehouse): object {
            $locationIds = $warehouse->locations->pluck('id');

            $inbound = StockMove::whereIn('to_location_id', $locationIds)->where('qty', '>', 0)->sum('qty');
            $outbound = StockMove::whereIn('from_location_id', $locationIds)->where('qty', '<', 0)->sum(DB::raw('ABS(qty)'));
            $moveCount = StockMove::where(fn ($q) => $q->whereIn('from_location_id', $locationIds)->orWhereIn('to_location_id', $locationIds))->count();
            $onHand = StockQuant::whereIn('location_id', $locationIds)->sum('qty');

            return (object) [
                'warehouse' => $warehouse,
                'move_count' => $moveCount,
                'inbound' => (string) $inbound,
                'outbound' => (string) $outbound,
                'on_hand' => (string) $onHand,
                'location_count' => $warehouse->locations->count(),
            ];
        });

        return view('inventory::reports.warehouse-analysis', [
            'stats' => $stats,
        ]);
    }

    public function consignment(): View
    {
        $rows = StockQuant::with(['product', 'location', 'ownerPartner'])
            ->whereNotNull('owner_partner_id')
            ->where('qty', '>', 0)
            ->get()
            ->groupBy('owner_partner_id');

        return view('inventory::reports.consignment', [
            'grouped' => $rows,
        ]);
    }
}
