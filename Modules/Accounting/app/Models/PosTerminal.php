<?php

namespace Modules\Accounting\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosTerminal extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'name', 'bank_journal_id', 'commission_rates',
        'settlement_days', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'commission_rates' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function bankJournal(): BelongsTo
    {
        return $this->belongsTo(Journal::class, 'bank_journal_id');
    }

    public function rateFor(int $installments): string
    {
        $rates = $this->commission_rates ?? [];

        return (string) ($rates[(string) $installments] ?? $rates[$installments] ?? '0');
    }
}
