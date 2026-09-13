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
                'key' => 'hr_leave_submitted',
                'name' => 'İzin Talebi Gönderildi (Yönetici bildirimi)',
                'subject' => '{{firma_adi}} — Yeni izin talebi: {{personel_adi}}',
                'body' => <<<'MD'
# Yeni İzin Talebi

**{{personel_adi}}** aşağıdaki talebi onayınıza gönderdi:

- **Tür:** {{izin_turu}}
- **Başlangıç:** {{baslangic_tarihi}}
- **Bitiş:** {{bitis_tarihi}}
- **Toplam gün:** {{gun_sayisi}}
- **Açıklama:** {{aciklama}}

Yanıtlamak için [onay ekranını]({{onay_baglantisi}}) açın.

{{uygulama_adi}}
MD,
            ],
            [
                'key' => 'hr_leave_approved',
                'name' => 'İzin Talebi Onaylandı (Personele bildirim)',
                'subject' => '{{firma_adi}} — İzin talebiniz onaylandı',
                'body' => <<<'MD'
# İzin Talebiniz Onaylandı

Sayın **{{personel_adi}}**,

Aşağıdaki izin talebiniz **{{onaylayan_adi}}** tarafından onaylandı:

- **Tür:** {{izin_turu}}
- **Başlangıç:** {{baslangic_tarihi}}
- **Bitiş:** {{bitis_tarihi}}
- **Toplam gün:** {{gun_sayisi}}

İyi tatiller dileriz.

{{uygulama_adi}}
MD,
            ],
            [
                'key' => 'hr_leave_rejected',
                'name' => 'İzin Talebi Reddedildi (Personele bildirim)',
                'subject' => '{{firma_adi}} — İzin talebiniz reddedildi',
                'body' => <<<'MD'
# İzin Talebiniz Reddedildi

Sayın **{{personel_adi}}**,

Aşağıdaki izin talebiniz **{{onaylayan_adi}}** tarafından reddedildi:

- **Tür:** {{izin_turu}}
- **Başlangıç:** {{baslangic_tarihi}}
- **Bitiş:** {{bitis_tarihi}}

**Sebep:** {{sebep}}

Daha fazla bilgi için birim yöneticinizle görüşebilirsiniz.

{{uygulama_adi}}
MD,
            ],
            [
                'key' => 'hr_leave_second_level_needed',
                'name' => 'İkinci Seviye Onay Bekleniyor (Genel Md.)',
                'subject' => '{{firma_adi}} — 2. seviye onay: {{personel_adi}}',
                'body' => <<<'MD'
# İkinci Seviye Onay Bekleniyor

**{{personel_adi}}** için aşağıdaki izin talebi birim yöneticisi tarafından onaylandı ve ikinci seviye onayınıza sunuldu:

- **Tür:** {{izin_turu}}
- **Başlangıç:** {{baslangic_tarihi}}
- **Bitiş:** {{bitis_tarihi}}
- **Birim yöneticisi:** {{birim_yoneticisi_adi}}

[Onay ekranını]({{onay_baglantisi}}) açın.

{{uygulama_adi}}
MD,
            ],
            [
                'key' => 'hr_leave_cancelled',
                'name' => 'İzin Talebi İptal Edildi',
                'subject' => '{{firma_adi}} — İzin talebi iptal: {{personel_adi}}',
                'body' => <<<'MD'
# İzin Talebi İptal Edildi

**{{personel_adi}}** aşağıdaki izin talebini iptal etti:

- **Tür:** {{izin_turu}}
- **Başlangıç:** {{baslangic_tarihi}}
- **Bitiş:** {{bitis_tarihi}}

{{uygulama_adi}}
MD,
            ],
            [
                'key' => 'hr_employee_announcement',
                'name' => 'Personel Duyurusu',
                'subject' => '{{firma_adi}} — Yeni duyuru: {{baslik}}',
                'body' => <<<'MD'
# {{baslik}}

{{icerik}}

—
{{yayinlayan_adi}}
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
