<?php

namespace Database\Seeders;

use App\Models\NotificationTemplate;
use Illuminate\Database\Seeder;

class NotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'key' => 'tenant_admin_invitation',
                'name' => 'Tenant Yöneticisi Davet E-postası',
                'subject' => '{{firma_adi}} — ERP Yönetici Hesabınız Oluşturuldu',
                'body' => <<<'MD'
# Hoş Geldiniz, {{yonetici_adi}}

**{{firma_adi}}** firması için ERP yönetici hesabınız oluşturuldu.

**Giriş bilgileriniz:**

- E-posta: {{yonetici_email}}
- Geçici şifre: `{{gecici_sifre}}`

İlk girişinizden sonra şifrenizi değiştirmenizi öneririz.

Teşekkürler,
{{uygulama_adi}}
MD,
            ],
        ];

        foreach ($templates as $attributes) {
            NotificationTemplate::updateOrCreate(
                ['tenant_id' => null, 'key' => $attributes['key']],
                $attributes,
            );
        }
    }
}
