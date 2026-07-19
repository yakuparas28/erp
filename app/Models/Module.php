<?php

namespace App\Models;

use Database\Factories\ModuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Platform genelinde modül kaydı — nWidart Modules/{Name} dizinleriyle
 * birebir eşleşir. Tenant'sızdır; runtime erişimi tenant_module_activations
 * üzerinden belirlenir (PRD 3.15-3.16).
 */
#[Fillable(['key', 'name', 'description', 'is_core'])]
class Module extends Model
{
    /** @use HasFactory<ModuleFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_core' => 'boolean',
        ];
    }

    public function licensePackages(): BelongsToMany
    {
        return $this->belongsToMany(LicensePackage::class, 'license_package_modules')->withTimestamps();
    }
}
