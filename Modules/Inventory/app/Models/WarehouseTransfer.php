<?php

namespace Modules\Inventory\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Inventory\Database\Factories\WarehouseTransferFactory;

class WarehouseTransfer extends Model
{
    /** @use HasFactory<WarehouseTransferFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'batch_id',
        'from_location_id',
        'to_location_id',
        'created_by',
        'status',
    ];

    protected static function newFactory(): WarehouseTransferFactory
    {
        return WarehouseTransferFactory::new();
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(TransferBatch::class, 'batch_id');
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(WarehouseTransferLine::class);
    }

    public function moves(): MorphMany
    {
        return $this->morphMany(StockMove::class, 'reference');
    }
}
