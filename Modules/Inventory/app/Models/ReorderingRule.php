<?php

namespace Modules\Inventory\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Inventory\Database\Factories\ReorderingRuleFactory;

class ReorderingRule extends Model
{
    /** @use HasFactory<ReorderingRuleFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'product_id',
        'location_id',
        'min_qty',
        'max_qty',
        'trigger_type',
    ];

    protected function casts(): array
    {
        return [
            'min_qty' => 'decimal:4',
            'max_qty' => 'decimal:4',
        ];
    }

    protected static function newFactory(): ReorderingRuleFactory
    {
        return ReorderingRuleFactory::new();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function suggestions(): HasMany
    {
        return $this->hasMany(ReplenishmentSuggestion::class);
    }
}
