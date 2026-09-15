<?php

namespace Modules\Accounting\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Models\Partner;

class CheckAndNote extends Model
{
    use BelongsToTenant;

    protected $table = 'checks_and_notes';

    public const TYPE_CHECK = 'check';

    public const TYPE_NOTE = 'promissory_note';

    public const DIR_INCOMING = 'incoming';

    public const DIR_OUTGOING = 'outgoing';

    public const STATUS_PORTFOLIO = 'portfolio';

    public const STATUS_ENDORSED = 'endorsed';

    public const STATUS_SENT_TO_BANK = 'sent_to_bank';

    public const STATUS_COLLECTED = 'collected';

    public const STATUS_BOUNCED = 'bounced';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_PAID = 'paid';

    protected $fillable = [
        'tenant_id', 'instrument_no', 'instrument_type', 'direction',
        'partner_id', 'drawer_name', 'drawee_bank_name', 'drawee_branch',
        'issue_date', 'maturity_date', 'amount', 'currency_id', 'status',
        'endorsed_to_partner_id', 'collection_bank_journal_id',
        'status_changed_at', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'maturity_date' => 'date',
            'status_changed_at' => 'date',
            'amount' => 'decimal:4',
        ];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function endorsedToPartner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'endorsed_to_partner_id');
    }

    public function collectionBankJournal(): BelongsTo
    {
        return $this->belongsTo(Journal::class, 'collection_bank_journal_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isIncoming(): bool
    {
        return $this->direction === self::DIR_INCOMING;
    }

    public function daysToMaturity(): int
    {
        return now()->startOfDay()->diffInDays($this->maturity_date->startOfDay(), false);
    }
}
