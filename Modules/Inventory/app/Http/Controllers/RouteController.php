<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Route as RouteModel;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Services\RouteService;
use Symfony\Component\HttpKernel\Exception\HttpException;

class RouteController extends Controller
{
    public function __construct(private readonly RouteService $routes) {}

    public function index(): View
    {
        return view('inventory::routes.index', [
            'routes' => RouteModel::withCount('rules')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(['name' => ['required', 'string', 'max:255']]);

        $route = RouteModel::create($validated);

        return redirect()->route('app.inventory.routes.show', $route)->with('status', __('Route created.'));
    }

    public function show(RouteModel $route): View
    {
        return view('inventory::routes.show', [
            'route' => $route->load(['rules' => fn ($query) => $query->orderBy('sequence'), 'rules.fromLocation', 'rules.toLocation']),
            'locations' => Location::where('type', 'internal')->orderBy('name')->get(),
            'products' => Product::where('product_type', '!=', 'service')->orderBy('name')->get(),
            'uoms' => Uom::orderBy('name')->get(),
        ]);
    }

    public function storeRule(Request $request, RouteModel $route): RedirectResponse
    {
        $validated = $request->validate([
            'from_location_id' => ['required', 'exists:locations,id'],
            'to_location_id' => ['required', 'exists:locations,id', 'different:from_location_id'],
            'action' => ['required', 'in:push,pull'],
            'sequence' => ['required', 'integer', 'min:0'],
        ]);

        $route->rules()->create($validated);

        return redirect()->route('app.inventory.routes.show', $route)->with('status', __('Route rule added.'));
    }

    public function execute(Request $request, RouteModel $route): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'uom_id' => ['required', 'exists:uoms,id'],
            'qty' => ['required', 'numeric', 'gt:0'],
        ]);

        $product = Product::findOrFail($validated['product_id']);
        $uom = Uom::findOrFail($validated['uom_id']);

        try {
            $this->routes->executePush($route, $product, (string) $validated['qty'], $uom);
        } catch (HttpException $e) {
            return back()->withErrors(['qty' => $e->getMessage()]);
        }

        return redirect()->route('app.inventory.routes.show', $route)->with('status', __('Push route executed.'));
    }
}
