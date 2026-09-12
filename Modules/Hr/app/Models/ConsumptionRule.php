<?php

namespace Modules\Hr\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Hr\Database\Factories\ConsumptionRuleFactory;

class ConsumptionRule extends Model
{
    /** @use HasFactory<ConsumptionRuleFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = ['tenant_id', 'code', 'category', 'name', 'legal_basis', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function newFactory(): ConsumptionRuleFactory
    {
        return ConsumptionRuleFactory::new();
    }
}
