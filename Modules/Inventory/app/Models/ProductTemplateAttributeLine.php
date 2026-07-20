<?php

namespace Modules\Inventory\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Database\Factories\ProductTemplateAttributeLineFactory;

class ProductTemplateAttributeLine extends Model
{
    /** @use HasFactory<ProductTemplateAttributeLineFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'product_template_id',
        'product_attribute_id',
    ];

    protected static function newFactory(): ProductTemplateAttributeLineFactory
    {
        return ProductTemplateAttributeLineFactory::new();
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ProductTemplate::class, 'product_template_id');
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(ProductAttribute::class, 'product_attribute_id');
    }
}
