<?php

namespace Modules\Inventory\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Database\Factories\RouteRuleFactory;

class RouteRule extends Model
{
    /** @use HasFactory<RouteRuleFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'route_id',
        'from_location_id',
        'to_location_id',
        'action',
        'sequence',
    ];

    protected static function newFactory(): RouteRuleFactory
    {
        return RouteRuleFactory::new();
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }
}
