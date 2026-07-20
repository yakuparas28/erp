<?php

namespace Modules\Inventory\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Inventory\Database\Factories\LandedCostFactory;

class LandedCost extends Model
{
    /** @use HasFactory<LandedCostFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'split_method',
        'status',
    ];

    protected static function newFactory(): LandedCostFactory
    {
        return LandedCostFactory::new();
    }

    public function lines(): HasMany
    {
        return $this->hasMany(LandedCostLine::class);
    }

    public function distributions(): HasMany
    {
        return $this->hasMany(LandedCostDistribution::class);
    }
}
