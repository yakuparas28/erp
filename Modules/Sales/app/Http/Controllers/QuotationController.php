<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Partner;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Services\SalesOrderService;

/**
 * Odoo `sale.order` Quotations menü karşılığı: aynı model, `status` filtreli
 * ayrı liste. Teklif oluşturma, gönderme ve "Siparişe Dönüştür" akışının
 * odaklandığı ekran.
 */
class QuotationController extends Controller
{
    public function __construct(private readonly SalesOrderService $salesOrders) {}

    public function index(): View
    {
        return view('sales::quotations.index', [
            'quotations' => SalesOrder::with(['partner', 'location', 'lines', 'creator'])
                ->whereIn('status', ['draft', 'quotation_sent'])
                ->latest()->get(),
            'customers' => Partner::where('is_customer', true)->orderBy('name')->get(),
            'locations' => Location::where('type', 'internal')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'partner_id' => ['required', Rule::exists('partners', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'location_id' => ['required', Rule::exists('locations', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
        ]);

        $so = $this->salesOrders->create(
            $tenantId,
            (int) $validated['partner_id'],
            (int) $validated['location_id'],
            $request->user(),
        );

        return redirect()->route('app.sales.orders.show', $so)->with('status', __('Quotation created.'));
    }
}
