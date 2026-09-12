<?php

namespace Modules\Inventory\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Database\Factories\OperationTypeFactory;

class OperationType extends Model
{
    /** @use HasFactory<OperationTypeFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'warehouse_id',
        'code',
        'name',
        'type',
        'sequence_prefix',
        'default_source_location_id',
        'default_destination_location_id',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    protected static function newFactory(): OperationTypeFactory
    {
        return OperationTypeFactory::new();
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function defaultSourceLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'default_source_location_id');
    }

    public function defaultDestinationLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'default_destination_location_id');
    }
}
