<?php

namespace Modules\Inventory\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Database\Factories\ProductAttributeValueFactory;

class ProductAttributeValue extends Model
{
    /** @use HasFactory<ProductAttributeValueFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'product_attribute_id',
        'value',
        'price_extra',
        'html_color',
        'image_path',
        'sequence',
        'active',
        'is_custom',
    ];

    protected function casts(): array
    {
        return [
            'price_extra' => 'decimal:4',
            'active' => 'boolean',
            'is_custom' => 'boolean',
            'sequence' => 'integer',
        ];
    }

    protected static function newFactory(): ProductAttributeValueFactory
    {
        return ProductAttributeValueFactory::new();
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(ProductAttribute::class, 'product_attribute_id');
    }
}
