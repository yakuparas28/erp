<?php

namespace Modules\Inventory\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Inventory\Database\Factories\ProductAttributeFactory;

class ProductAttribute extends Model
{
    /** @use HasFactory<ProductAttributeFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'creation_mode',
        'display_type',
        'sequence',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'sequence' => 'integer',
        ];
    }

    protected static function newFactory(): ProductAttributeFactory
    {
        return ProductAttributeFactory::new();
    }

    public function values(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class);
    }
}
