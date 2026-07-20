<?php

namespace Modules\Purchase\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\StockMove;
use Modules\Inventory\Models\Uom;
use Modules\Purchase\Database\Factories\PurchaseOrderLineFactory;

class PurchaseOrderLine extends Model
{
    /** @use HasFactory<PurchaseOrderLineFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'purchase_order_id',
        'product_id',
        'uom_id',
        'qty',
        'unit_price',
        'tax_rate_id',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:4',
            'unit_price' => 'decimal:4',
        ];
    }

    protected static function newFactory(): PurchaseOrderLineFactory
    {
        return PurchaseOrderLineFactory::new();
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class);
    }

    /**
     * Teslim alınan miktar (PRD 3.10, 3-yönlü eşleştirme): ayrı bir sayaç
     * kolonu yerine bu satıra bağlı gerçek stock_moves toplamından türetilir.
     */
    public function receivedQty(): string
    {
        $sum = StockMove::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant_id)
            ->where('reference_type', 'purchase_order_line')
            ->where('reference_id', $this->id)
            ->sum('qty');

        return bcadd((string) $sum, '0', 4);
    }
}
