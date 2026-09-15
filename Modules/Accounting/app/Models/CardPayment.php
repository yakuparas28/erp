<?php

namespace Modules\Accounting\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Models\Partner;

class CardPayment extends Model
{
    use BelongsToTenant;

    public const STATUS_PENDING = 'pending_settlement';

    public const STATUS_SETTLED = 'settled';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'tenant_id', 'pos_terminal_id', 'partner_id',
        'gross_amount', 'installments', 'commission_rate',
        'commission_amount', 'net_amount', 'transaction_date',
        'expected_settlement_date', 'status', 'settled_at',
        'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'gross_amount' => 'decimal:4',
            'commission_rate' => 'decimal:3',
            'commission_amount' => 'decimal:4',
            'net_amount' => 'decimal:4',
            'installments' => 'integer',
            'transaction_date' => 'date',
            'expected_settlement_date' => 'date',
            'settled_at' => 'date',
        ];
    }

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(PosTerminal::class, 'pos_terminal_id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
