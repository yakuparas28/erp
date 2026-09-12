<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Services\SalesOrderService;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Odoo `portal` denkliği: müşteri, e-postadaki linkle kimlik doğrulaması
 * olmadan teklifi görüntüler, kabul veya reddeder. access_token her SO'ya
 * özgü rastgele bir dizedir; başka bir token'la başka SO'ya erişilemez.
 */
class PortalQuoteController extends Controller
{
    public function __construct(private readonly SalesOrderService $salesOrders) {}

    public function show(string $token): View
    {
        $so = $this->resolveByToken($token);

        return view('sales::portal.quote', ['so' => $so]);
    }

    public function accept(string $token): RedirectResponse
    {
        $so = $this->resolveByToken($token);

        if ($so->status === 'confirmed') {
            return redirect()->route('portal.quote', ['token' => $token])
                ->with('status', __('This quotation is already confirmed.'));
        }

        try {
            $this->salesOrders->confirm($so, null, byCustomer: true);
        } catch (HttpException $e) {
            return back()->withErrors(['portal' => $e->getMessage()]);
        }

        return redirect()->route('portal.quote', ['token' => $token])
            ->with('status', __('Thank you! Your order has been confirmed.'));
    }

    public function decline(string $token): RedirectResponse
    {
        $so = $this->resolveByToken($token);

        try {
            $this->salesOrders->declineByCustomer($so);
        } catch (HttpException $e) {
            return back()->withErrors(['portal' => $e->getMessage()]);
        }

        return redirect()->route('portal.quote', ['token' => $token])
            ->with('status', __('Quotation declined.'));
    }

    private function resolveByToken(string $token): SalesOrder
    {
        $so = SalesOrder::withoutGlobalScopes()->where('access_token', $token)->first();

        abort_if($so === null, 404);

        return $so->load(['partner', 'lines.product', 'lines.uom']);
    }
}
