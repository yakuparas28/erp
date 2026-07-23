<?php

namespace Modules\Accounting\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Accounting\Database\Factories\FxRevaluationFactory;

class FxRevaluation extends Model
{
    /** @use HasFactory<FxRevaluationFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'invoice_id',
        'payment_id',
        'type',
        'difference_amount',
        'revaluation_date',
    ];

    protected function casts(): array
    {
        return [
            'difference_amount' => 'decimal:4',
            'revaluation_date' => 'date',
        ];
    }

    protected static function newFactory(): FxRevaluationFactory
    {
        return FxRevaluationFactory::new();
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
