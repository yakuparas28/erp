<?php

namespace App\Models;

use Database\Factories\NotificationTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Düzenlenebilir bildirim şablonu — tenant_id null ise platform varsayılanı.
 * Gövde markdown'dır; {{degisken}} yer tutucuları render sırasında değiştirilir.
 */
#[Fillable(['tenant_id', 'key', 'name', 'subject', 'body'])]
class NotificationTemplate extends Model
{
    /** @use HasFactory<NotificationTemplateFactory> */
    use HasFactory;
}
