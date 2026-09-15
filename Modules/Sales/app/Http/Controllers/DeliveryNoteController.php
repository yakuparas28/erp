<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\View\View;
use Modules\Sales\Models\DeliveryNote;

class DeliveryNoteController extends Controller
{
    public function index(): View
    {
        return view('sales::delivery-notes.index', [
            'notes' => DeliveryNote::with(['salesOrder.partner', 'lines.product', 'creator'])
                ->latest('delivery_date')
                ->latest('id')
                ->paginate(50),
        ]);
    }

    public function show(DeliveryNote $note): View
    {
        return view('sales::delivery-notes.show', [
            'note' => $note->load(['salesOrder.partner', 'lines.product', 'lines.uom', 'creator']),
        ]);
    }
}
