<?php

namespace Modules\Inventory\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Database\Factories\StockValuationLayerFactory;

class StockValuationLayer extends Model
{
    /** @use HasFactory<StockValuationLayerFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'product_id',
        'stock_move_id',
        'qty',
        'unit_cost',
        'remaining_value',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:4',
            'unit_cost' => 'decimal:4',
            'remaining_value' => 'decimal:4',
        ];
    }

    protected static function newFactory(): StockValuationLayerFactory
    {
        return StockValuationLayerFactory::new();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function stockMove(): BelongsTo
    {
        return $this->belongsTo(StockMove::class);
    }
}
