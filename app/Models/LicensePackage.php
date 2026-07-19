<?php

namespace App\Models;

use Database\Factories\LicensePackageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'monthly_price'])]
class LicensePackage extends Model
{
    /** @use HasFactory<LicensePackageFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'monthly_price' => 'decimal:4',
        ];
    }

    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'license_package_modules')->withTimestamps();
    }
}
