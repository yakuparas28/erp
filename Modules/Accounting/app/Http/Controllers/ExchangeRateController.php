<?php

namespace Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Accounting\Models\Currency;
use Modules\Accounting\Models\ExchangeRate;
use Modules\Accounting\Services\ExchangeRateService;

class ExchangeRateController extends Controller
{
    public function __construct(private readonly ExchangeRateService $exchangeRates) {}

    public function index(): View
    {
        return view('accounting::exchange-rates.index', [
            'rates' => ExchangeRate::with('currency')->orderByDesc('rate_date')->get(),
            'currencies' => Currency::where('is_functional', false)->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'currency_id' => ['required', 'exists:currencies,id'],
            'rate_date' => ['required', 'date'],
            'buy_rate' => ['required', 'numeric', 'gt:0'],
            'sell_rate' => ['required', 'numeric', 'gt:0'],
        ]);

        $this->exchangeRates->recordManualRate(
            $request->user()->tenant_id,
            (int) $validated['currency_id'],
            (string) $validated['rate_date'],
            (string) $validated['buy_rate'],
            (string) $validated['sell_rate'],
        );

        return redirect()->route('app.accounting.exchange-rates.index')->with('status', __('Exchange rate recorded.'));
    }
}
