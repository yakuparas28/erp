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
            [
                'key' => 'user_invitation',
                'name' => 'Kullanıcı Davet E-postası',
                'subject' => '{{firma_adi}} — ERP Hesabınız Oluşturuldu',
                'body' => <<<'MD'
# Merhaba {{kullanici_adi}}

**{{firma_adi}}** bünyesinde sizin için bir ERP hesabı oluşturuldu.

**Giriş bilgileriniz:**

- E-posta: {{kullanici_email}}
- Geçici şifre: `{{gecici_sifre}}`

İlk girişinizden sonra şifrenizi değiştirmenizi öneririz.

Teşekkürler,
{{uygulama_adi}}
MD,
            ],
            [
                'key' => 'sales_order_quotation',
                'name' => 'Satış Teklifi E-postası',
                'subject' => '{{firma_adi}} — Teklif #{{teklif_no}}',
                'body' => <<<'MD'
# Sayın {{musteri_adi}},

**{{firma_adi}}** olarak talebinize istinaden **#{{teklif_no}}** numaralı teklifimizi aşağıda sunuyoruz.

- **Teklif Toplam:** {{toplam}}
- **Geçerlilik Tarihi:** {{gecerlilik_tarihi}}

Teklifi görüntülemek için [bu bağlantıya]({{teklif_baglantisi}}) tıklayabilirsiniz.

Teklifiniz için teşekkür ederiz. Sorularınız için bize ulaşmaktan çekinmeyin.

Saygılarımızla,
{{firma_adi}}
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
