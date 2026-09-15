<?php

namespace Modules\Accounting\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankStatementLine extends Model
{
    use BelongsToTenant;

    public const STATUS_UNMATCHED = 'unmatched';

    public const STATUS_AUTO = 'auto_matched';

    public const STATUS_MANUAL = 'manual_matched';

    public const STATUS_IGNORED = 'ignored';

    protected $fillable = [
        'tenant_id', 'bank_statement_id', 'transaction_date',
        'description', 'debit', 'credit', 'running_balance',
        'external_ref', 'matched_type', 'matched_id', 'status',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'debit' => 'decimal:4',
            'credit' => 'decimal:4',
            'running_balance' => 'decimal:4',
        ];
    }

    public function statement(): BelongsTo
    {
        return $this->belongsTo(BankStatement::class, 'bank_statement_id');
    }

    public function isCredit(): bool
    {
        return bccomp((string) $this->credit, '0', 4) > 0;
    }

    public function amount(): string
    {
        return $this->isCredit() ? (string) $this->credit : (string) $this->debit;
    }
}
