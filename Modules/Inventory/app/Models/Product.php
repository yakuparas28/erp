<?php

namespace Modules\Inventory\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Inventory\Database\Factories\ProductFactory;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'product_category_id',
        'uom_id',
        'product_template_id',
        'name',
        'sku',
        'track_by',
        'product_type',
        'is_kit',
        'cost_method',
        'standard_cost',
        'avco_unit_cost',
    ];

    protected function casts(): array
    {
        return [
            'is_kit' => 'boolean',
            'standard_cost' => 'decimal:4',
            'avco_unit_cost' => 'decimal:4',
            'current_stock' => 'decimal:4',
        ];
    }

    protected static function newFactory(): ProductFactory
    {
        return ProductFactory::new();
    }

    /**
     * Hizmet ürünlerde cost_method yalnızca 'standard' olabilir (PRD 3.17):
     * FIFO/AVCO'nun dayandığı parti/katman kavramı hizmette anlamsızdır.
     */
    protected static function booted(): void
    {
        static::saving(function (self $product): void {
            abort_if(
                $product->product_type === 'service' && $product->cost_method !== 'standard',
                422,
                __('Service products can only use the standard cost method.'),
            );
        });
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function barcodes(): HasMany
    {
        return $this->hasMany(ProductBarcode::class);
    }

    public function lots(): HasMany
    {
        return $this->hasMany(ProductLot::class);
    }

    public function quants(): HasMany
    {
        return $this->hasMany(StockQuant::class);
    }

    public function kitComponents(): HasMany
    {
        return $this->hasMany(ProductKitComponent::class, 'kit_product_id');
    }
}
