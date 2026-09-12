<?php

namespace Modules\Inventory\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Inventory\Database\Factories\PartnerFactory;

/**
 * Birleşik müşteri/tedarikçi (PRD 3.10/3.11, Odoo res.partner esinli).
 * Inventory (çekirdek) modülünde yaşar çünkü hem Satış hem Satınalma
 * paylaşır.
 */
class Partner extends Model
{
    /** @use HasFactory<PartnerFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'phone',
        'address',
        'tax_number',
        'is_customer',
        'is_supplier',
        'payment_term_days',
    ];

    protected function casts(): array
    {
        return [
            'is_customer' => 'boolean',
            'is_supplier' => 'boolean',
        ];
    }

    protected static function newFactory(): PartnerFactory
    {
        return PartnerFactory::new();
    }

    public function suppliedProducts(): HasMany
    {
        return $this->hasMany(Product::class, 'default_supplier_id');
    }
}
