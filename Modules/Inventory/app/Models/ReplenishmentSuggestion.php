<?php

namespace Modules\Inventory\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Database\Factories\ReplenishmentSuggestionFactory;

class ReplenishmentSuggestion extends Model
{
    /** @use HasFactory<ReplenishmentSuggestionFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'reordering_rule_id',
        'suggested_qty',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'suggested_qty' => 'decimal:4',
        ];
    }

    protected static function newFactory(): ReplenishmentSuggestionFactory
    {
        return ReplenishmentSuggestionFactory::new();
    }

    public function reorderingRule(): BelongsTo
    {
        return $this->belongsTo(ReorderingRule::class);
    }
}
