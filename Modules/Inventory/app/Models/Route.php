<?php

namespace Modules\Inventory\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Inventory\Database\Factories\RouteFactory;

class Route extends Model
{
    /** @use HasFactory<RouteFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
    ];

    protected static function newFactory(): RouteFactory
    {
        return RouteFactory::new();
    }

    public function rules(): HasMany
    {
        return $this->hasMany(RouteRule::class);
    }
}
