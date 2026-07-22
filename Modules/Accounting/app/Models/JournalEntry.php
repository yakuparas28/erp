<?php

namespace Modules\Accounting\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Accounting\Database\Factories\JournalEntryFactory;

class JournalEntry extends Model
{
    /** @use HasFactory<JournalEntryFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'journal_id',
        'entry_date',
        'reference_type',
        'reference_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
        ];
    }

    protected static function newFactory(): JournalEntryFactory
    {
        return JournalEntryFactory::new();
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
