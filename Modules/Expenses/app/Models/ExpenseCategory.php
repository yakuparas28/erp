<?php

namespace Modules\Expenses\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseCategory extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'code', 'name', 'description', 'unit_price', 'unit_label',
        'expense_account_code', 'is_reinvoiceable', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:4',
            'is_reinvoiceable' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function isFlatRate(): bool
    {
        return (float) $this->unit_price > 0;
    }
}
