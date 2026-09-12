<?php

namespace Modules\Inventory\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Database\Factories\ScrapFactory;

class Scrap extends Model
{
    /** @use HasFactory<ScrapFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'product_id',
        'source_location_id',
        'scrap_location_id',
        'lot_id',
        'uom_id',
        'qty',
        'reason',
        'done_by_user_id',
        'scrapped_at',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:4',
            'scrapped_at' => 'datetime',
        ];
    }

    protected static function newFactory(): ScrapFactory
    {
        return ScrapFactory::new();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function sourceLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'source_location_id');
    }

    public function scrapLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'scrap_location_id');
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(ProductLot::class, 'lot_id');
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class);
    }

    public function doneBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'done_by_user_id');
    }
}
