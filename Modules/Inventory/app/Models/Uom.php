<?php

namespace Modules\Inventory\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Database\Factories\UomFactory;

class Uom extends Model
{
    /** @use HasFactory<UomFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'uom_category_id',
        'name',
        'factor',
        'is_reference',
    ];

    protected function casts(): array
    {
        return [
            'is_reference' => 'boolean',
            'factor' => 'decimal:6',
        ];
    }

    protected static function newFactory(): UomFactory
    {
        return UomFactory::new();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(UomCategory::class, 'uom_category_id');
    }
}
