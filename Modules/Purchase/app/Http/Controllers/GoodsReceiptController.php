<?php

namespace Modules\Purchase\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\View\View;
use Modules\Purchase\Models\GoodsReceipt;

class GoodsReceiptController extends Controller
{
    public function index(): View
    {
        return view('purchase::goods-receipts.index', [
            'receipts' => GoodsReceipt::with(['purchaseOrder.partner', 'lines.product', 'warehouseLocation', 'creator'])
                ->latest('receipt_date')
                ->latest('id')
                ->paginate(50),
        ]);
    }

    public function show(GoodsReceipt $receipt): View
    {
        return view('purchase::goods-receipts.show', [
            'receipt' => $receipt->load(['purchaseOrder.partner', 'lines.product', 'lines.uom', 'warehouseLocation', 'creator']),
        ]);
    }
}
