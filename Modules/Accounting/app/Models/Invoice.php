<?php

namespace Modules\Accounting\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Accounting\Database\Factories\InvoiceFactory;
use Modules\Inventory\Models\Partner;

class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'partner_id',
        'type',
        'source_type',
        'source_id',
        'status',
    ];

    protected static function newFactory(): InvoiceFactory
    {
        return InvoiceFactory::new();
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function subtotal(): string
    {
        return $this->lines->reduce(fn (string $carry, InvoiceLine $line) => bcadd($carry, $line->subtotal(), 4), '0.0000');
    }

    public function taxTotal(): string
    {
        return $this->lines->reduce(fn (string $carry, InvoiceLine $line) => bcadd($carry, $line->taxAmount(), 4), '0.0000');
    }

    public function total(): string
    {
        return bcadd($this->subtotal(), $this->taxTotal(), 4);
    }

    public function paidTotal(): string
    {
        return (string) $this->allocations()->sum('allocated_amount');
    }

    public function remainingBalance(): string
    {
        return bcsub($this->total(), $this->paidTotal(), 4);
    }
}
