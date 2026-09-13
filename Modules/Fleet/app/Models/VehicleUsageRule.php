<?php

namespace Modules\Fleet\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleUsageRule extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'icerik_html', 'updated_by_id'];

    public static function forTenant(int $tenantId): self
    {
        return static::firstOrCreate(['tenant_id' => $tenantId], ['icerik_html' => '']);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }
}
