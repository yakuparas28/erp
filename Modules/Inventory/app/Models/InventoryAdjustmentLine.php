<?php

namespace Modules\Inventory\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Database\Factories\InventoryAdjustmentLineFactory;

class InventoryAdjustmentLine extends Model
{
    /** @use HasFactory<InventoryAdjustmentLineFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'inventory_adjustment_id',
        'product_id',
        'lot_id',
        'counted_qty',
        'theoretical_qty',
    ];

    protected function casts(): array
    {
        return [
            'counted_qty' => 'decimal:4',
            'theoretical_qty' => 'decimal:4',
        ];
    }

    protected static function newFactory(): InventoryAdjustmentLineFactory
    {
        return InventoryAdjustmentLineFactory::new();
    }

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(InventoryAdjustment::class, 'inventory_adjustment_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
