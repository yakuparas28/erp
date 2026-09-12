<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductBarcode;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Services\StockMoveService;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Odoo `stock.barcode` operatör ekranı denkliği. Bu tam mobil PWA değil
 * (o Faz F4'ün büyük hâli); klavye/USB barkod okuyucularla masaüstünden
 * hızlı sayım/hareket girişi için sadeleştirilmiş web sayfası. Faz 4 sync
 * API'leri mobil operatör uygulaması için hazır — bu sayfa ondan bağımsız.
 */
class BarcodeController extends Controller
{
    public function index(): View
    {
        return view('inventory::barcode.index', [
            'locations' => Location::where('type', 'internal')->orderBy('name')->get(),
        ]);
    }

    public function lookup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'barcode' => ['required', 'string', 'max:255'],
        ]);

        $product = $this->findByBarcodeOrSku($validated['barcode']);

        if ($product === null) {
            return response()->json(['found' => false], 404);
        }

        return response()->json([
            'found' => true,
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'uom' => $product->uom?->name,
                'uom_id' => $product->uom_id,
            ],
        ]);
    }

    /**
     * Odoo `stock.barcode` gibi barcode ana alanı → ek barkodlar → SKU
     * (default_code) sırasıyla arar. İlk eşleşme kazanır.
     */
    private function findByBarcodeOrSku(string $code): ?Product
    {
        $direct = Product::where(fn ($q) => $q->where('barcode', $code)->orWhere('sku', $code))
            ->with('uom')
            ->first();

        if ($direct !== null) {
            return $direct;
        }

        $extra = ProductBarcode::where('barcode', $code)->with('product.uom')->first();

        return $extra?->product;
    }

    public function move(Request $request): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'barcode' => ['required', 'string', 'max:255'],
            'from_location_id' => [
                'nullable',
                Rule::exists('locations', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'to_location_id' => [
                'required',
                Rule::exists('locations', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'qty' => ['required', 'numeric', 'gt:0'],
        ]);

        $product = $this->findByBarcodeOrSku($validated['barcode']);

        if ($product === null) {
            return back()->withErrors(['barcode' => __('Unknown barcode.')]);
        }

        $uom = $product->uom;

        try {
            if (! empty($validated['from_location_id'])) {
                $this->stockMoves($tenantId, $product, (int) $validated['from_location_id'], null, '-'.$validated['qty'], $uom);
            }
            $this->stockMoves($tenantId, $product, null, (int) $validated['to_location_id'], (string) $validated['qty'], $uom);
        } catch (HttpException $e) {
            return back()->withErrors(['barcode' => $e->getMessage()]);
        }

        return back()->with('status', __('Move recorded: :product qty :qty', ['product' => $product->name, 'qty' => $validated['qty']]));
    }

    private function stockMoves(int $tenantId, Product $product, ?int $from, ?int $to, string $qty, Uom $uom): void
    {
        app(StockMoveService::class)->move(
            tenantId: $tenantId,
            product: $product,
            fromLocationId: $from,
            toLocationId: $to,
            qty: $qty,
            uom: $uom,
            referenceType: 'barcode_operator',
            referenceId: 0,
        );
    }
}
