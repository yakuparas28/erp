<?php

namespace Modules\Accounting\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankStatement extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'bank_journal_id', 'original_filename', 'file_hash',
        'period_start', 'period_end', 'opening_balance', 'closing_balance',
        'total_lines', 'matched_lines', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'opening_balance' => 'decimal:4',
            'closing_balance' => 'decimal:4',
        ];
    }

    public function bankJournal(): BelongsTo
    {
        return $this->belongsTo(Journal::class, 'bank_journal_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BankStatementLine::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function matchPct(): int
    {
        return $this->total_lines > 0 ? (int) round(($this->matched_lines / $this->total_lines) * 100) : 0;
    }
}
