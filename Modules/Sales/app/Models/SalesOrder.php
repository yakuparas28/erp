<?php

namespace Modules\Sales\Models;

use App\Concerns\HasApproval;
use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Partner;
use Modules\Inventory\Models\Route;
use Modules\Sales\Database\Factories\SalesOrderFactory;

class SalesOrder extends Model
{
    /** @use HasFactory<SalesOrderFactory> */
    use BelongsToTenant, HasApproval, HasFactory;

    protected $fillable = [
        'tenant_id',
        'partner_id',
        'location_id',
        'route_id',
        'delivery_carrier_id',
        'tracking_number',
        'created_by',
        'status',
        'sent_at',
        'validity_date',
        'access_token',
        'customer_confirmed_at',
        'customer_declined_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'validity_date' => 'date',
            'customer_confirmed_at' => 'datetime',
            'customer_declined_at' => 'datetime',
        ];
    }

    protected static function newFactory(): SalesOrderFactory
    {
        return SalesOrderFactory::new();
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function inventoryRoute(): BelongsTo
    {
        return $this->belongsTo(Route::class, 'route_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function deliveryCarrier(): BelongsTo
    {
        return $this->belongsTo(DeliveryCarrier::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SalesOrderLine::class);
    }
}
