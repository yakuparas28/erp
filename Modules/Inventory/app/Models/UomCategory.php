<?php

namespace Modules\Inventory\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Inventory\Database\Factories\UomCategoryFactory;

class UomCategory extends Model
{
    /** @use HasFactory<UomCategoryFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
    ];

    protected static function newFactory(): UomCategoryFactory
    {
        return UomCategoryFactory::new();
    }

    public function uoms(): HasMany
    {
        return $this->hasMany(Uom::class);
    }
}
