<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\Approval\ApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Services\SalesOrderService;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Satış siparişi onay akışı — iki farklı iş akışı destekler:
 * 'quotation' (teklif gönderimi öncesi) ve 'sales_order' (sipariş
 * onayı öncesi). Tenant Admin, Onay Akışları ekranında bu subject
 * type'lardan istediğini kurar, tutar eşiği Muhasebe Ayarları'nda.
 */
class SalesApprovalController extends Controller
{
    public function submitQuotation(SalesOrder $so, SalesOrderService $sales): RedirectResponse
    {
        try {
            $sales->submitForQuotationApproval($so, request()->user());
        } catch (HttpException $e) {
            return back()->withErrors(['approval' => $e->getMessage()]);
        }

        return back()->with('status', __('Quotation submitted for approval.'));
    }

    public function submitConfirmation(SalesOrder $so, SalesOrderService $sales): RedirectResponse
    {
        try {
            $sales->submitForConfirmationApproval($so, request()->user());
        } catch (HttpException $e) {
            return back()->withErrors(['approval' => $e->getMessage()]);
        }

        return back()->with('status', __('Sales order submitted for approval.'));
    }

    public function approve(Request $request, SalesOrder $so, string $type, ApprovalService $approvals): RedirectResponse
    {
        abort_unless(in_array($type, ['quotation', 'sales_order'], true), 404);
        $approval = $so->approvalFor($type);
        abort_if($approval === null, 404);

        try {
            $approvals->approve($approval, $request->user(), $request->input('comment'));
        } catch (HttpException $e) {
            return back()->withErrors(['approval' => $e->getMessage()]);
        }

        return back()->with('status', __('Approved.'));
    }

    public function reject(Request $request, SalesOrder $so, string $type, ApprovalService $approvals): RedirectResponse
    {
        abort_unless(in_array($type, ['quotation', 'sales_order'], true), 404);
        $approval = $so->approvalFor($type);
        abort_if($approval === null, 404);

        try {
            $approvals->reject($approval, $request->user(), $request->input('comment'));
        } catch (HttpException $e) {
            return back()->withErrors(['approval' => $e->getMessage()]);
        }

        return back()->with('status', __('Rejected.'));
    }
}
