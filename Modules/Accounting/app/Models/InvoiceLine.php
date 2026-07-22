<?php

namespace Modules\Accounting\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Accounting\Database\Factories\InvoiceLineFactory;
use Modules\Inventory\Models\Product;

class InvoiceLine extends Model
{
    /** @use HasFactory<InvoiceLineFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'invoice_id',
        'product_id',
        'qty',
        'unit_price',
        'tax_rate_id',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:4',
            'unit_price' => 'decimal:4',
        ];
    }

    protected static function newFactory(): InvoiceLineFactory
    {
        return InvoiceLineFactory::new();
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

    public function subtotal(): string
    {
        return bcmul($this->qty, $this->unit_price, 4);
    }

    public function taxAmount(): string
    {
        if ($this->tax_rate_id === null) {
            return '0.0000';
        }

        return bcmul($this->subtotal(), bcdiv($this->taxRate->percentage, '100', 6), 4);
    }
}
