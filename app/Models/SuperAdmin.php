<?php

namespace App\Models;

use Database\Factories\SuperAdminFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Platform yöneticisi — hiçbir tenant'a bağlı DEĞİLDİR (tenant_id kolonu yok),
 * ayrı 'super_admin' Sanctum guard'ı ile /api/central/* altında doğrulanır.
 * BelongsToTenant scope'u bu modele hiç uygulanmaz (PRD Bölüm 2).
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class SuperAdmin extends Authenticatable
{
    /** @use HasFactory<SuperAdminFactory> */
    use HasApiTokens, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }
}
