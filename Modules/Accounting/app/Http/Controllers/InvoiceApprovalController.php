<?php

namespace Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\Approval\ApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Services\InvoiceService;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Fatura onay akışı — tutar eşiği aşan faturaları onaya gönderme,
 * onaylama, reddetme. Post akışı InvoiceService::post()'ta korunur;
 * bu controller yalnızca approval kaydını yönetir.
 */
class InvoiceApprovalController extends Controller
{
    public function submit(Invoice $invoice, InvoiceService $invoices): RedirectResponse
    {
        try {
            $invoices->submitForApproval($invoice, request()->user());
        } catch (HttpException $e) {
            return back()->withErrors(['invoice' => $e->getMessage()]);
        }

        return back()->with('status', __('Invoice submitted for approval.'));
    }

    public function approve(Request $request, Invoice $invoice, ApprovalService $approvals): RedirectResponse
    {
        $approval = $invoice->approval;
        abort_if($approval === null, 404);

        try {
            $approvals->approve($approval, $request->user(), $request->input('comment'));
        } catch (HttpException $e) {
            return back()->withErrors(['approval' => $e->getMessage()]);
        }

        return back()->with('status', __('Invoice approved.'));
    }

    public function reject(Request $request, Invoice $invoice, ApprovalService $approvals): RedirectResponse
    {
        $approval = $invoice->approval;
        abort_if($approval === null, 404);

        try {
            $approvals->reject($approval, $request->user(), $request->input('comment'));
        } catch (HttpException $e) {
            return back()->withErrors(['approval' => $e->getMessage()]);
        }

        return back()->with('status', __('Invoice rejected.'));
    }
}
