<?php

namespace Modules\Inventory\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Inventory\Database\Factories\WarehouseFactory;

class Warehouse extends Model
{
    /** @use HasFactory<WarehouseFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'reception_steps',
        'delivery_steps',
        'input_location_id',
        'quality_location_id',
        'output_location_id',
        'pack_location_id',
    ];

    protected static function newFactory(): WarehouseFactory
    {
        return WarehouseFactory::new();
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function inputLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'input_location_id');
    }

    public function qualityLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'quality_location_id');
    }

    public function outputLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'output_location_id');
    }

    public function packLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'pack_location_id');
    }
}
