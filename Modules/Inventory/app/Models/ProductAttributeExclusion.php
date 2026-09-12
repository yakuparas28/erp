<?php

namespace Modules\Inventory\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Database\Factories\ProductAttributeExclusionFactory;

class ProductAttributeExclusion extends Model
{
    /** @use HasFactory<ProductAttributeExclusionFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'product_attribute_value_id',
        'excluded_value_id',
        'product_template_id',
    ];

    protected static function newFactory(): ProductAttributeExclusionFactory
    {
        return ProductAttributeExclusionFactory::new();
    }

    public function value(): BelongsTo
    {
        return $this->belongsTo(ProductAttributeValue::class, 'product_attribute_value_id');
    }

    public function excludedValue(): BelongsTo
    {
        return $this->belongsTo(ProductAttributeValue::class, 'excluded_value_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ProductTemplate::class, 'product_template_id');
    }
}
