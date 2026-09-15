<?php

namespace Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Accounting\Models\CardPayment;
use Modules\Accounting\Models\CheckAndNote;
use Modules\Accounting\Services\CashFlowService;

class CashFlowController extends Controller
{
    public function __construct(private readonly CashFlowService $cashflow) {}

    public function index(Request $request): View
    {
        $tenantId = $request->user()->tenant_id;

        return view('accounting::cash-flow.index', [
            'balances' => $this->cashflow->accountBalances($tenantId),
            'incomingBuckets' => $this->cashflow->upcomingChecksBuckets($tenantId, 'incoming'),
            'outgoingBuckets' => $this->cashflow->upcomingChecksBuckets($tenantId, 'outgoing'),
            'projection' => $this->cashflow->projection($tenantId),
            'urgentIncomingChecks' => CheckAndNote::where('direction', 'incoming')
                ->whereIn('status', [CheckAndNote::STATUS_PORTFOLIO, CheckAndNote::STATUS_SENT_TO_BANK])
                ->whereBetween('maturity_date', [now()->startOfDay(), now()->addDays(7)])
                ->with('partner')->orderBy('maturity_date')->take(10)->get(),
            'urgentOutgoingChecks' => CheckAndNote::where('direction', 'outgoing')
                ->where('status', CheckAndNote::STATUS_PORTFOLIO)
                ->whereBetween('maturity_date', [now()->startOfDay(), now()->addDays(7)])
                ->with('partner')->orderBy('maturity_date')->take(10)->get(),
            'pendingCards' => CardPayment::where('status', CardPayment::STATUS_PENDING)
                ->with(['terminal', 'partner'])->orderBy('expected_settlement_date')->take(10)->get(),
        ]);
    }
}
