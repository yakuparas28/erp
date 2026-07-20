<?php

namespace Modules\Inventory\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Database\Factories\ProductKitComponentFactory;

class ProductKitComponent extends Model
{
    /** @use HasFactory<ProductKitComponentFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'kit_product_id',
        'component_product_id',
        'qty',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:4',
        ];
    }

    protected static function newFactory(): ProductKitComponentFactory
    {
        return ProductKitComponentFactory::new();
    }

    /**
     * İç içe kit yasağı (PRD 3.17): bir bileşen kendisi is_kit=true olamaz.
     * Explode anında da ayrıca doğrulanır; bu, hatalı satırın en baştan
     * oluşmasını engeller.
     */
    protected static function booted(): void
    {
        static::creating(function (self $component): void {
            $isNestedKit = Product::withoutGlobalScopes()
                ->whereKey($component->component_product_id)
                ->where('is_kit', true)
                ->exists();

            abort_if($isNestedKit, 422, __('A kit component cannot itself be a kit.'));
        });
    }

    public function kitProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'kit_product_id');
    }

    public function componentProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'component_product_id');
    }
}
