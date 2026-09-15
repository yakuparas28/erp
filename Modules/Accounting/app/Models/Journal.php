<?php

namespace Modules\Accounting\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Accounting\Database\Factories\JournalFactory;

class Journal extends Model
{
    /** @use HasFactory<JournalFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'type',
        'code',
        'bank_name',
        'iban',
        'account_no',
        'currency_id',
        'chart_of_account_id',
        'opening_balance',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): JournalFactory
    {
        return JournalFactory::new();
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function chartOfAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'chart_of_account_id');
    }

    public function isCashOrBank(): bool
    {
        return in_array($this->type, ['cash', 'bank'], true);
    }
}
