<?php

namespace Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Accounting\Models\Currency;
use Modules\Accounting\Models\ExchangeRate;
use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Models\Payment;

class CurrencyController extends Controller
{
    public function index(): View
    {
        return view('accounting::currencies.index', [
            'currencies' => Currency::orderByDesc('is_functional')->orderByDesc('active')->orderBy('code')->get(),
        ]);
    }

    public function show(Currency $currency): View
    {
        return view('accounting::currencies.show', [
            'currency' => $currency,
            'rates' => ExchangeRate::where('currency_id', $currency->id)->orderByDesc('rate_date')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'size:3',
                'regex:/^[A-Z]{3}$/',
                Rule::unique('currencies', 'code')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'name' => ['required', 'string', 'max:255'],
            'symbol' => ['nullable', 'string', 'max:8'],
            'position' => ['required', 'in:before,after'],
            'rounding' => ['required', 'numeric', 'gt:0'],
        ]);

        Currency::create($validated + ['is_functional' => false, 'active' => true]);

        return redirect()->route('app.accounting.currencies.index')->with('status', __('Currency added.'));
    }

    public function update(Request $request, Currency $currency): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'symbol' => ['nullable', 'string', 'max:8'],
            'position' => ['required', 'in:before,after'],
            'rounding' => ['required', 'numeric', 'gt:0'],
        ]);

        $currency->update($validated);

        return redirect()->route('app.accounting.currencies.index')->with('status', __('Currency updated.'));
    }

    public function archive(Currency $currency): RedirectResponse
    {
        if ($currency->is_functional) {
            return back()->withErrors(['currency' => __('The functional currency cannot be archived.')]);
        }

        $currency->update(['active' => false]);

        return redirect()->route('app.accounting.currencies.index')->with('status', __('Currency archived.'));
    }

    public function restore(Currency $currency): RedirectResponse
    {
        $currency->update(['active' => true]);

        return redirect()->route('app.accounting.currencies.index')->with('status', __('Currency restored.'));
    }

    public function destroy(Currency $currency): RedirectResponse
    {
        if ($currency->is_functional) {
            return back()->withErrors(['currency' => __('The functional currency cannot be deleted.')]);
        }

        if ($currency->active) {
            return back()->withErrors(['currency' => __('Archive the currency before deleting it.')]);
        }

        $isUsed = ExchangeRate::where('currency_id', $currency->id)->exists()
            || Invoice::where('currency_id', $currency->id)->exists()
            || Payment::where('currency_id', $currency->id)->exists();

        if ($isUsed) {
            return back()->withErrors(['currency' => __('This currency is in use and cannot be deleted.')]);
        }

        $currency->delete();

        return redirect()->route('app.accounting.currencies.index')->with('status', __('Currency deleted.'));
    }
}
