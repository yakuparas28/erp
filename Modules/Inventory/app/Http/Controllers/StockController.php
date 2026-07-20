<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\View\View;
use Modules\Inventory\Models\StockQuant;

class StockController extends Controller
{
    public function index(): View
    {
        $quants = StockQuant::with(['product.uom', 'location.warehouse', 'lot'])
            ->where('qty', '>', 0)
            ->get()
            ->sortBy(fn (StockQuant $quant) => $quant->product->name);

        return view('inventory::stock.index', ['quants' => $quants]);
    }
}
