<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Inventory\Models\LandedCost;
use Modules\Inventory\Models\LandedCostLine;
use Modules\Inventory\Models\StockMove;
use Modules\Inventory\Services\LandedCostService;
use Symfony\Component\HttpKernel\Exception\HttpException;

class LandedCostController extends Controller
{
    public function __construct(private readonly LandedCostService $landedCosts) {}

    public function index(): View
    {
        return view('inventory::landed-costs.index', [
            'landedCosts' => LandedCost::withCount(['lines', 'distributions'])->latest()->get(),
        ]);
    }

    public function show(LandedCost $landedCost): View
    {
        $landedCost->load(['lines', 'distributions.stockMove.product', 'distributions.stockMove.toLocation']);

        // Eligible: inbound moves (qty > 0) from receipts, not yet in this landed cost's distribution
        $existingMoveIds = $landedCost->distributions->pluck('stock_move_id');

        $eligibleMoves = collect();
        if ($landedCost->status === 'draft') {
            $eligibleMoves = StockMove::where('qty', '>', 0)
                ->whereIn('reference_type', ['purchase_order_line'])
                ->whereNotIn('id', $existingMoveIds)
                ->with(['product', 'toLocation'])
                ->latest()->limit(200)->get();
        }

        return view('inventory::landed-costs.show', [
            'landedCost' => $landedCost,
            'eligibleMoves' => $eligibleMoves,
            'total' => $landedCost->lines->reduce(fn (string $c, $line) => bcadd($c, (string) $line->amount, 4), '0'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'split_method' => ['required', 'in:by_quantity,by_weight,by_volume,by_current_cost'],
        ]);

        $landedCost = LandedCost::create($validated + ['status' => 'draft']);

        return redirect()->route('app.inventory.landed-costs.show', $landedCost)->with('status', __('Landed cost created.'));
    }

    public function storeLine(Request $request, LandedCost $landedCost): RedirectResponse
    {
        abort_unless($landedCost->status === 'draft', 422, __('Only draft landed costs can be edited.'));

        $validated = $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
        ]);

        $landedCost->lines()->create([
            'tenant_id' => $landedCost->tenant_id,
            'description' => $validated['description'],
            'amount' => $validated['amount'],
        ]);

        return back()->with('status', __('Cost line added.'));
    }

    public function destroyLine(LandedCostLine $line): RedirectResponse
    {
        abort_unless($line->landedCost->status === 'draft', 422, __('Only draft landed costs can be edited.'));

        $line->delete();

        return back()->with('status', __('Cost line removed.'));
    }

    public function validateLandedCost(Request $request, LandedCost $landedCost): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'stock_move_ids' => ['required', 'array', 'min:1'],
            'stock_move_ids.*' => [
                Rule::exists('stock_moves', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
        ]);

        try {
            $this->landedCosts->validate(
                $landedCost,
                array_map('intval', $validated['stock_move_ids']),
                $request->user(),
            );
        } catch (HttpException $e) {
            return back()->withErrors(['landed_cost' => $e->getMessage()]);
        }

        return redirect()->route('app.inventory.landed-costs.show', $landedCost)->with('status', __('Landed cost validated and distributed.'));
    }
}
