<?php

namespace Modules\Inventory\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Inventory\Database\Factories\StorageCategoryFactory;

class StorageCategory extends Model
{
    /** @use HasFactory<StorageCategoryFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'max_weight',
        'allow_new_product',
    ];

    protected function casts(): array
    {
        return [
            'max_weight' => 'decimal:4',
        ];
    }

    protected static function newFactory(): StorageCategoryFactory
    {
        return StorageCategoryFactory::new();
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }
}
