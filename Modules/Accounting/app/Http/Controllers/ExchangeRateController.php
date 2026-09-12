<?php

namespace Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Accounting\Models\Currency;
use Modules\Accounting\Models\ExchangeRate;
use Modules\Accounting\Services\ExchangeRateService;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ExchangeRateController extends Controller
{
    public function __construct(private readonly ExchangeRateService $exchangeRates) {}

    public function index(): View
    {
        return view('accounting::exchange-rates.index', [
            'rates' => ExchangeRate::with('currency')->orderByDesc('rate_date')->get(),
            'currencies' => Currency::where('is_functional', false)->where('active', true)->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'currency_id' => [
                'required',
                Rule::exists('currencies', 'id')->where(fn ($query) => $query
                    ->where('tenant_id', $request->user()->tenant_id)
                    ->where('is_functional', false)),
            ],
            'rate_date' => ['required', 'date'],
            'buy_rate' => ['required', 'numeric', 'gt:0'],
            'sell_rate' => ['required', 'numeric', 'gt:0'],
        ]);

        $currencyId = (int) $validated['currency_id'];
        $newBuyRate = (string) $validated['buy_rate'];

        $this->exchangeRates->recordManualRate(
            $request->user()->tenant_id,
            $currencyId,
            (string) $validated['rate_date'],
            $newBuyRate,
            (string) $validated['sell_rate'],
        );

        $previous = ExchangeRate::where('currency_id', $currencyId)
            ->where('rate_date', '<', $validated['rate_date'])
            ->orderByDesc('rate_date')->first();

        if ($previous !== null && $this->deviatesMoreThan($newBuyRate, (string) $previous->buy_rate, 20)) {
            return redirect()->route('app.accounting.exchange-rates.index')
                ->with('warning', __('The new rate differs by more than 20% from the previous rate — please double-check.'));
        }

        return redirect()->route('app.accounting.exchange-rates.index')->with('status', __('Exchange rate recorded.'));
    }

    public function syncFromTcmb(Request $request): RedirectResponse
    {
        try {
            $this->exchangeRates->syncFromTcmb($request->user()->tenant_id);
        } catch (HttpException $e) {
            return back()->with('warning', __('TCMB rates could not be fetched — the bulletin may not be published yet (weekends/holidays).'));
        }

        return redirect()->route('app.accounting.exchange-rates.index')
            ->with('status', __("Today's TCMB rates have been imported."));
    }

    /**
     * Odoo'nun `_onchange_rate_warning`'ının sunucu tarafı karşılığı: yeni kur
     * bir önceki kurdan yüzde eşikten fazla saparsa uyarı gösterir.
     */
    private function deviatesMoreThan(string $new, string $previous, int $thresholdPercent): bool
    {
        $delta = bcsub($new, $previous, 6);
        $absoluteDelta = bccomp($delta, '0', 6) < 0 ? bcmul($delta, '-1', 6) : $delta;
        $threshold = bcmul($previous, (string) ($thresholdPercent / 100), 6);

        return bccomp($absoluteDelta, $threshold, 6) > 0;
    }
}
