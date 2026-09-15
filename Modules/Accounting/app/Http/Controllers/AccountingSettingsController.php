<?php

namespace Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Muhasebe modülüne özel tenant ayarları. Şu an sadece fatura tutar
 * bazlı onay eşiği. NULL = onay akışı kapalı.
 */
class AccountingSettingsController extends Controller
{
    public function edit(Request $request): View
    {
        return view('accounting::settings.index', [
            'tenant' => $request->user()->tenant,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'invoice_approval_threshold' => ['nullable', 'numeric', 'min:0'],
            'quotation_approval_threshold' => ['nullable', 'numeric', 'min:0'],
            'sales_order_approval_threshold' => ['nullable', 'numeric', 'min:0'],
            'purchase_order_approval_threshold' => ['nullable', 'numeric', 'min:0'],
        ]);

        $tenant = Tenant::findOrFail($request->user()->tenant_id);
        $tenant->update([
            'invoice_approval_threshold' => $validated['invoice_approval_threshold'] ?? null,
            'quotation_approval_threshold' => $validated['quotation_approval_threshold'] ?? null,
            'sales_order_approval_threshold' => $validated['sales_order_approval_threshold'] ?? null,
            'purchase_order_approval_threshold' => $validated['purchase_order_approval_threshold'] ?? null,
        ]);

        return back()->with('status', __('Settings saved.'));
    }
}
