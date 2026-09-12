<?php

namespace Modules\Inventory\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Models\TransferBatch;
use Modules\Inventory\Models\WarehouseTransfer;

/**
 * Odoo `stock.picking.batch` denkliği: birden fazla transferi bir toplu
 * pakette bağlar; `complete()` çağrıldığında sırayla her transferi
 * `WarehouseTransferService::complete()`'a delegasyonla tamamlar.
 */
class TransferBatchService
{
    public function __construct(private readonly WarehouseTransferService $transfers) {}

    public function create(int $tenantId, string $name): TransferBatch
    {
        $batch = new TransferBatch([
            'name' => $name,
            'status' => 'draft',
        ]);
        $batch->tenant_id = $tenantId;
        $batch->save();

        return $batch;
    }

    public function addTransfer(TransferBatch $batch, WarehouseTransfer $transfer): void
    {
        abort_if($batch->status !== 'draft', 422, __('Only draft batches can accept new transfers.'));
        abort_if($transfer->status !== 'draft', 422, __('Only draft transfers can be added to a batch.'));
        abort_if($transfer->tenant_id !== $batch->tenant_id, 422, __('Transfer belongs to another tenant.'));

        $transfer->update(['batch_id' => $batch->id]);
    }

    public function removeTransfer(WarehouseTransfer $transfer): void
    {
        abort_if($transfer->status !== 'draft', 422, __('Only draft transfers can be removed from a batch.'));

        $transfer->update(['batch_id' => null]);
    }

    public function complete(TransferBatch $batch, User $doneBy): void
    {
        abort_unless(in_array($batch->status, ['draft', 'in_progress'], true), 422, __('Only draft or in-progress batches can be completed.'));
        abort_if($batch->transfers->isEmpty(), 422, __('Batch has no transfers to complete.'));

        DB::transaction(function () use ($batch, $doneBy): void {
            $batch->update(['status' => 'in_progress']);

            foreach ($batch->transfers as $transfer) {
                if ($transfer->status === 'draft') {
                    $this->transfers->complete($transfer);
                }
            }

            $batch->update([
                'status' => 'done',
                'done_by_user_id' => $doneBy->id,
            ]);
        });
    }

    public function cancel(TransferBatch $batch): void
    {
        abort_unless(in_array($batch->status, ['draft', 'in_progress'], true), 422, __('Only draft or in-progress batches can be cancelled.'));

        DB::transaction(function () use ($batch): void {
            foreach ($batch->transfers as $transfer) {
                if ($transfer->status === 'draft') {
                    $transfer->update(['batch_id' => null]);
                }
            }

            $batch->update(['status' => 'cancelled']);
        });
    }
}
