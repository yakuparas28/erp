<?php

namespace Modules\Inventory\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Inventory\Database\Factories\PackageTypeFactory;

class PackageType extends Model
{
    /** @use HasFactory<PackageTypeFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'barcode',
        'height',
        'width',
        'packaging_length',
        'max_weight',
    ];

    protected function casts(): array
    {
        return [
            'height' => 'decimal:4',
            'width' => 'decimal:4',
            'packaging_length' => 'decimal:4',
            'max_weight' => 'decimal:4',
        ];
    }

    protected static function newFactory(): PackageTypeFactory
    {
        return PackageTypeFactory::new();
    }
}
