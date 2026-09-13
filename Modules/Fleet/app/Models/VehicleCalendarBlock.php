<?php

namespace Modules\Fleet\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Fleet\Database\Factories\VehicleCalendarBlockFactory;

class VehicleCalendarBlock extends Model
{
    /** @use HasFactory<VehicleCalendarBlockFactory> */
    use BelongsToTenant, HasFactory;

    public const TYPE_BAKIM = 'bakim';

    public const TYPE_MUAYENE = 'muayene';

    public const TYPE_BLOKE = 'bloke';

    protected $fillable = ['tenant_id', 'vehicle_id', 'block_type', 'start_date', 'end_date', 'aciklama', 'created_by_id'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date'];
    }

    protected static function newFactory(): VehicleCalendarBlockFactory
    {
        return VehicleCalendarBlockFactory::new();
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
