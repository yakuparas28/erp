<?php

namespace Modules\Purchase\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\Approval\ApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Purchase\Models\PurchaseOrder;
use Modules\Purchase\Services\PurchaseOrderService;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PurchaseApprovalController extends Controller
{
    public function submit(PurchaseOrder $po, PurchaseOrderService $purchase): RedirectResponse
    {
        try {
            $purchase->submitForConfirmationApproval($po, request()->user());
        } catch (HttpException $e) {
            return back()->withErrors(['approval' => $e->getMessage()]);
        }

        return back()->with('status', __('Purchase order submitted for approval.'));
    }

    public function approve(Request $request, PurchaseOrder $po, ApprovalService $approvals): RedirectResponse
    {
        $approval = $po->approvalFor('purchase_order');
        abort_if($approval === null, 404);

        try {
            $approvals->approve($approval, $request->user(), $request->input('comment'));
        } catch (HttpException $e) {
            return back()->withErrors(['approval' => $e->getMessage()]);
        }

        return back()->with('status', __('Approved.'));
    }

    public function reject(Request $request, PurchaseOrder $po, ApprovalService $approvals): RedirectResponse
    {
        $approval = $po->approvalFor('purchase_order');
        abort_if($approval === null, 404);

        try {
            $approvals->reject($approval, $request->user(), $request->input('comment'));
        } catch (HttpException $e) {
            return back()->withErrors(['approval' => $e->getMessage()]);
        }

        return back()->with('status', __('Rejected.'));
    }
}
