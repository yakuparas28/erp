<?php

namespace Modules\Inventory\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Inventory\Database\Factories\ProductTemplateFactory;

class ProductTemplate extends Model
{
    /** @use HasFactory<ProductTemplateFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'base_price',
    ];

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:4',
        ];
    }

    protected static function newFactory(): ProductTemplateFactory
    {
        return ProductTemplateFactory::new();
    }

    public function attributeLines(): HasMany
    {
        return $this->hasMany(ProductTemplateAttributeLine::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(Product::class, 'product_template_id');
    }
}
