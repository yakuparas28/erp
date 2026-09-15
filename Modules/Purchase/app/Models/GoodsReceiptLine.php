<?php

namespace Modules\Purchase\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Uom;

class GoodsReceiptLine extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'goods_receipt_id', 'purchase_order_line_id',
        'product_id', 'uom_id', 'qty',
    ];

    protected function casts(): array
    {
        return ['qty' => 'decimal:4'];
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class);
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
