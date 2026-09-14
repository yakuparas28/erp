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

    public const ENTITY_COMPANY = 'company';

    public const ENTITY_INDIVIDUAL = 'individual';

    public const EINVOICE_NONE = 'none';

    public const EINVOICE_ARSIV = 'e_arsiv';

    public const EINVOICE_FATURA = 'e_fatura';

    protected $fillable = [
        'tenant_id',
        'partner_code',
        'name',
        'entity_type',
        'group_code',
        'email',
        'phone',
        'fax',
        'contact_person',
        'address',
        'country',
        'city',
        'district',
        'tax_number',
        'tax_office',
        'national_id',
        'e_invoice_status',
        'e_invoice_alias',
        'is_customer',
        'is_supplier',
        'payment_term_days',
        'account_code_receivable',
        'account_code_payable',
        'currency_code',
        'credit_limit',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_customer' => 'boolean',
            'is_supplier' => 'boolean',
            'is_active' => 'boolean',
            'credit_limit' => 'decimal:2',
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
