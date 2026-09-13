<?php

namespace Modules\Fleet\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class FleetTaskSetting extends Model
{
    use BelongsToTenant;

    protected $table = 'fleet_task_settings';

    protected $fillable = [
        'tenant_id', 'critical_window_enabled', 'critical_window_days', 'mtv_reminder_days',
        'critical_window_last_run_at', 'critical_window_last_run_notified', 'mtv_last_run_at',
    ];

    protected function casts(): array
    {
        return [
            'critical_window_enabled' => 'boolean',
            'critical_window_days' => 'integer',
            'mtv_reminder_days' => 'integer',
            'critical_window_last_run_at' => 'datetime',
            'critical_window_last_run_notified' => 'integer',
            'mtv_last_run_at' => 'datetime',
        ];
    }

    public static function forTenant(int $tenantId): self
    {
        return static::firstOrCreate(['tenant_id' => $tenantId]);
    }
}
