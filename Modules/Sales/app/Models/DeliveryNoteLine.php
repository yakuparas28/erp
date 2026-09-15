<?php

namespace Modules\Sales\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Uom;

class DeliveryNoteLine extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'delivery_note_id', 'sales_order_line_id',
        'product_id', 'uom_id', 'qty',
    ];

    protected function casts(): array
    {
        return ['qty' => 'decimal:4'];
    }

    public function deliveryNote(): BelongsTo
    {
        return $this->belongsTo(DeliveryNote::class);
    }

    public function salesOrderLine(): BelongsTo
    {
        return $this->belongsTo(SalesOrderLine::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class);
    }
}
