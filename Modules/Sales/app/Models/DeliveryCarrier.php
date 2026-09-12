<?php

namespace Modules\Sales\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Sales\Database\Factories\DeliveryCarrierFactory;

class DeliveryCarrier extends Model
{
    /** @use HasFactory<DeliveryCarrierFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'tracking_url_template',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    protected static function newFactory(): DeliveryCarrierFactory
    {
        return DeliveryCarrierFactory::new();
    }

    public function trackingUrlFor(?string $trackingNumber): ?string
    {
        if ($trackingNumber === null || $trackingNumber === '' || $this->tracking_url_template === null) {
            return null;
        }

        return str_replace('{tracking_number}', urlencode($trackingNumber), $this->tracking_url_template);
    }
}
