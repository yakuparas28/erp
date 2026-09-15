<?php

namespace App\Models;

use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'accounting_mode',
        'tax_number',
        'tax_office',
        'email',
        'phone',
        'address',
        'invoice_approval_threshold',
    ];

    protected function casts(): array
    {
        return [
            'invoice_approval_threshold' => 'decimal:4',
        ];
    }

    /** @var list<string>|null */
    private ?array $activeModuleKeysCache = null;

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Bu tenant'ta modül anahtarı aktif mi? Core modüller (is_core=true) her
     * zaman aktif kabul edilir. Sonuç request başı memoize'lıdır — sidebar
     * her @module çağrısında yeni sorgu atmasın diye.
     */
    public function hasActiveModule(string $moduleKey): bool
    {
        if ($this->activeModuleKeysCache === null) {
            $activated = DB::table('tenant_module_activations')
                ->join('modules', 'modules.id', '=', 'tenant_module_activations.module_id')
                ->where('tenant_module_activations.tenant_id', $this->id)
                ->where('tenant_module_activations.is_active', true)
                ->pluck('modules.key')
                ->all();

            $core = DB::table('modules')->where('is_core', true)->pluck('key')->all();

            $this->activeModuleKeysCache = array_values(array_unique(array_merge($core, $activated)));
        }

        return in_array($moduleKey, $this->activeModuleKeysCache, true);
    }
}
