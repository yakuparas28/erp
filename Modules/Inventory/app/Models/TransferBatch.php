<?php

namespace Modules\Inventory\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Inventory\Database\Factories\TransferBatchFactory;

class TransferBatch extends Model
{
    /** @use HasFactory<TransferBatchFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'status',
        'done_by_user_id',
    ];

    protected static function newFactory(): TransferBatchFactory
    {
        return TransferBatchFactory::new();
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(WarehouseTransfer::class, 'batch_id');
    }

    public function doneBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'done_by_user_id');
    }
}
