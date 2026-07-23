<?php

namespace Modules\Accounting\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Accounting\Database\Factories\PaymentFactory;
use Modules\Inventory\Models\Partner;

class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'partner_id',
        'journal_id',
        'amount',
        'payment_date',
        'currency_id',
        'exchange_rate_used',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'payment_date' => 'date',
            'exchange_rate_used' => 'decimal:6',
        ];
    }

    protected static function newFactory(): PaymentFactory
    {
        return PaymentFactory::new();
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function exchangeRateOrOne(): string
    {
        return $this->exchange_rate_used ?? '1.000000';
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function allocatedTotal(): string
    {
        $sum = $this->allocations()->sum('allocated_amount');

        return bcadd((string) $sum, '0', 4);
    }

    public function unallocatedAmount(): string
    {
        return bcsub($this->amount, $this->allocatedTotal(), 4);
    }
}
