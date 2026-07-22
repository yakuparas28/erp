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
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'payment_date' => 'date',
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
