<?php

namespace Modules\Inventory\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Inventory\Database\Factories\SyncBatchFactory;

class SyncBatch extends Model
{
    /** @use HasFactory<SyncBatchFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'batch_uuid',
    ];

    protected static function newFactory(): SyncBatchFactory
    {
        return SyncBatchFactory::new();
    }
}
