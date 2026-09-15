<?php

namespace Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Accounting\Models\CardPayment;
use Modules\Accounting\Models\PosTerminal;
use Modules\Accounting\Services\CardPaymentService;
use Modules\Inventory\Models\Partner;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CardPaymentController extends Controller
{
    public function __construct(private readonly CardPaymentService $cards) {}

    public function index(): View
    {
        return view('accounting::card-payments.index', [
            'payments' => CardPayment::with(['terminal', 'partner'])->latest('transaction_date')->latest('id')->get(),
            'terminals' => PosTerminal::where('is_active', true)->orderBy('name')->get(),
            'partners' => Partner::where('is_customer', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;
        $validated = $request->validate([
            'pos_terminal_id' => [
                'required',
                Rule::exists('pos_terminals', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)->where('is_active', true)),
            ],
            'partner_id' => ['required', Rule::exists('partners', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'gross_amount' => ['required', 'numeric', 'gt:0'],
            'installments' => ['required', 'integer', 'min:1', 'max:36'],
            'transaction_date' => ['required', 'date'],
        ]);

        $terminal = PosTerminal::findOrFail($validated['pos_terminal_id']);

        try {
            $this->cards->record(
                $terminal,
                (int) $validated['partner_id'],
                (string) $validated['gross_amount'],
                (int) $validated['installments'],
                (string) $validated['transaction_date'],
                $request->user(),
            );
        } catch (HttpException $e) {
            return back()->withErrors(['card' => $e->getMessage()]);
        }

        return back()->with('status', __('Card payment recorded.'));
    }

    public function settle(Request $request, CardPayment $card): RedirectResponse
    {
        $validated = $request->validate([
            'settled_at' => ['nullable', 'date'],
        ]);

        try {
            $this->cards->settle($card, $validated['settled_at'] ?? null);
        } catch (HttpException $e) {
            return back()->withErrors(['card' => $e->getMessage()]);
        }

        return back()->with('status', __('Settled to bank.'));
    }
}
