<?php

namespace Modules\Fleet\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Fleet\Database\Factories\ProjectFactory;

class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = ['tenant_id', 'ad', 'baslangic_tarihi', 'aciklama', 'aktif'];

    protected function casts(): array
    {
        return ['aktif' => 'boolean', 'baslangic_tarihi' => 'date'];
    }

    protected static function newFactory(): ProjectFactory
    {
        return ProjectFactory::new();
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'proje_id');
    }
}
