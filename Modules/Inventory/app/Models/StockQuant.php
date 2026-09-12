<?php

namespace Modules\Inventory\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Database\Factories\StockQuantFactory;

class StockQuant extends Model
{
    /** @use HasFactory<StockQuantFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'product_id',
        'location_id',
        'lot_id',
        'owner_partner_id',
        'qty',
        'reserved_qty',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:4',
            'reserved_qty' => 'decimal:4',
        ];
    }

    protected static function newFactory(): StockQuantFactory
    {
        return StockQuantFactory::new();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(ProductLot::class, 'lot_id');
    }

    public function ownerPartner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'owner_partner_id');
    }
}
