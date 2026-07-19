<?php

namespace App\Models;

use Database\Factories\MailSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * SMTP ayarları — tenant_id null ise platform (Süper Admin) ayarıdır.
 * Platform yönetimli tablo; BelongsToTenant bilinçli kullanılmaz.
 */
#[Fillable(['tenant_id', 'host', 'port', 'username', 'password', 'encryption', 'from_address', 'from_name', 'is_active'])]
class MailSetting extends Model
{
    /** @use HasFactory<MailSettingFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'is_active' => 'boolean',
            'port' => 'integer',
        ];
    }

    /**
     * On-demand mailer konfigürasyonu üretir.
     *
     * @return array<string, mixed>
     */
    public function toMailerConfig(): array
    {
        return [
            'transport' => 'smtp',
            'scheme' => $this->encryption === 'ssl' ? 'smtps' : 'smtp',
            'host' => $this->host,
            'port' => $this->port,
            'username' => $this->username,
            'password' => $this->password,
            'timeout' => null,
        ];
    }
}
