<?php

namespace Modules\Inventory\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Database\Factories\LandedCostDistributionFactory;

class LandedCostDistribution extends Model
{
    /** @use HasFactory<LandedCostDistributionFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'landed_cost_id',
        'stock_move_id',
        'allocated_amount',
    ];

    protected function casts(): array
    {
        return [
            'allocated_amount' => 'decimal:4',
        ];
    }

    protected static function newFactory(): LandedCostDistributionFactory
    {
        return LandedCostDistributionFactory::new();
    }

    public function landedCost(): BelongsTo
    {
        return $this->belongsTo(LandedCost::class);
    }

    public function stockMove(): BelongsTo
    {
        return $this->belongsTo(StockMove::class);
    }
}
