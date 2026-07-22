<?php

namespace Modules\Inventory\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Inventory\Database\Factories\ProductCategoryFactory;

class ProductCategory extends Model
{
    /** @use HasFactory<ProductCategoryFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'stock_input_account_id',
        'stock_output_account_id',
        'expense_account_id',
        'income_account_id',
    ];

    protected static function newFactory(): ProductCategoryFactory
    {
        return ProductCategoryFactory::new();
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function stockInputAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'stock_input_account_id');
    }

    public function stockOutputAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'stock_output_account_id');
    }

    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'expense_account_id');
    }

    public function incomeAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'income_account_id');
    }
}
