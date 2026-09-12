<?php

namespace Modules\Sales\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Accounting\Models\TaxRate;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Uom;
use Modules\Sales\Database\Factories\SalesOrderLineFactory;

class SalesOrderLine extends Model
{
    /** @use HasFactory<SalesOrderLineFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'sales_order_id',
        'product_id',
        'uom_id',
        'qty',
        'unit_price',
        'delivered_qty',
        'reserved_qty',
        'tax_rate_id',
        'custom_values',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'delivered_qty' => 'decimal:4',
            'reserved_qty' => 'decimal:4',
            'custom_values' => 'array',
        ];
    }

    protected static function newFactory(): SalesOrderLineFactory
    {
        return SalesOrderLineFactory::new();
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class);
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }
}
