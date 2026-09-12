<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Inventory\Models\TransferBatch;
use Modules\Inventory\Models\WarehouseTransfer;
use Modules\Inventory\Services\TransferBatchService;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TransferBatchController extends Controller
{
    public function __construct(private readonly TransferBatchService $batches) {}

    public function index(): View
    {
        return view('inventory::transfer-batches.index', [
            'batches' => TransferBatch::with(['transfers.fromLocation', 'transfers.toLocation'])->latest()->get(),
        ]);
    }

    public function show(TransferBatch $batch): View
    {
        return view('inventory::transfer-batches.show', [
            'batch' => $batch->load(['transfers.fromLocation', 'transfers.toLocation', 'doneBy']),
            'availableTransfers' => WarehouseTransfer::whereNull('batch_id')->where('status', 'draft')
                ->with(['fromLocation', 'toLocation'])->orderByDesc('id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $batch = $this->batches->create($request->user()->tenant_id, $validated['name']);

        return redirect()->route('app.inventory.transfer-batches.show', $batch)->with('status', __('Batch created.'));
    }

    public function addTransfer(Request $request, TransferBatch $batch): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'transfer_id' => [
                'required',
                Rule::exists('warehouse_transfers', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
        ]);

        $transfer = WarehouseTransfer::findOrFail($validated['transfer_id']);

        try {
            $this->batches->addTransfer($batch, $transfer);
        } catch (HttpException $e) {
            return back()->withErrors(['batch' => $e->getMessage()]);
        }

        return back()->with('status', __('Transfer added to batch.'));
    }

    public function removeTransfer(WarehouseTransfer $transfer): RedirectResponse
    {
        $batchId = $transfer->batch_id;

        try {
            $this->batches->removeTransfer($transfer);
        } catch (HttpException $e) {
            return back()->withErrors(['batch' => $e->getMessage()]);
        }

        return redirect()->route('app.inventory.transfer-batches.show', $batchId)->with('status', __('Transfer removed from batch.'));
    }

    public function complete(Request $request, TransferBatch $batch): RedirectResponse
    {
        try {
            $this->batches->complete($batch, $request->user());
        } catch (HttpException $e) {
            return back()->withErrors(['batch' => $e->getMessage()]);
        }

        return back()->with('status', __('Batch completed.'));
    }

    public function cancel(TransferBatch $batch): RedirectResponse
    {
        try {
            $this->batches->cancel($batch);
        } catch (HttpException $e) {
            return back()->withErrors(['batch' => $e->getMessage()]);
        }

        return back()->with('status', __('Batch cancelled.'));
    }
}
