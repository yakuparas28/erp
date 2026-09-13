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
                'key' => 'fleet_reservation_created',
                'name' => 'Filo — Yeni Rezervasyon Talebi (Filo Yöneticisine)',
                'subject' => '{{firma_adi}} — {{aktif_sofor}} kişisinin araç talebi ({{plaka}})',
                'body' => <<<'MD'
# Yeni Araç Rezervasyon Talebi

- **Talep No:** #{{reservation_id}}
- **Talep Eden:** {{aktif_sofor}}
- **Araç:** {{plaka}} — {{marka_model}}
- **Proje:** {{proje}}
- **Planlanan Alış:** {{planlanan_alis}}
- **Planlanan Teslim:** {{planlanan_teslim}}
- **Talep Tarihi:** {{talep_tarihi}}

[Onay ekranını]({{onay_baglantisi}}) açın.

{{uygulama_adi}}
MD,
            ],
            [
                'key' => 'fleet_reservation_approved',
                'name' => 'Filo — Rezervasyon Onaylandı (Şoförlere)',
                'subject' => '{{firma_adi}} — Araç talebiniz onaylandı ({{plaka}})',
                'body' => <<<'MD'
# Rezervasyon Onaylandı

Sayın **{{recipient_name}}**,

- **Araç:** {{plaka}} — {{marka_model}}
- **Proje:** {{proje}}
- **Planlanan Alış:** {{planlanan_alis}}
- **Planlanan Teslim:** {{planlanan_teslim}}

Alış zamanında [alış ekranını]({{alis_baglantisi}}) açarak KM'yi teyit edin.

{{uygulama_adi}}
MD,
            ],
            [
                'key' => 'fleet_reservation_rejected',
                'name' => 'Filo — Rezervasyon Reddedildi (Talep sahibine)',
                'subject' => '{{firma_adi}} — Araç talebiniz reddedildi ({{plaka}})',
                'body' => <<<'MD'
# Rezervasyon Reddedildi

Sayın **{{recipient_name}}**,

- **Araç:** {{plaka}} — {{marka_model}}
- **Proje:** {{proje}}

**Sebep:** {{red_aciklamasi}}

{{uygulama_adi}}
MD,
            ],
            [
                'key' => 'fleet_pickup_confirmed',
                'name' => 'Filo — Alış Yapıldı (Filo Yöneticisine)',
                'subject' => '{{firma_adi}} — Alış yapıldı: {{plaka}}',
                'body' => <<<'MD'
# Araç Alışı Tamamlandı

- **Şoför:** {{aktif_sofor}}
- **Araç:** {{plaka}} — {{marka_model}}
- **Alış KM:** {{alis_km}}
- **Alış Tarihi:** {{alis_tarihi}}

{{uygulama_adi}}
MD,
            ],
            [
                'key' => 'fleet_mileage_deviation',
                'name' => 'Filo — KM Sapması Bildirimi',
                'subject' => '{{firma_adi}} — KM Sapması: {{plaka}}',
                'body' => <<<'MD'
# KM Sapması Tespit Edildi

- **Şoför:** {{aktif_sofor}}
- **Araç:** {{plaka}} — {{marka_model}}
- **Sistem KM:** {{sistem_km}}
- **Okunan KM:** {{okunan_km}}
- **Sapma:** {{sapma}} km

{{uygulama_adi}}
MD,
            ],
            [
                'key' => 'fleet_delivery_completed',
                'name' => 'Filo — Teslim Tamamlandı (Filo Yöneticisine)',
                'subject' => '{{firma_adi}} — Teslim tamamlandı: {{plaka}}',
                'body' => <<<'MD'
# Araç Teslimi Tamamlandı

- **Şoför:** {{aktif_sofor}}
- **Araç:** {{plaka}} — {{marka_model}}
- **Teslim KM:** {{teslim_km}}
- **Teslim Tarihi:** {{teslim_tarihi}}
- **Beyan:** {{beyan_durumu}}

{{uygulama_adi}}
MD,
            ],
            [
                'key' => 'fleet_vehicle_blocked',
                'name' => 'Filo — Araç Bakıma Çekildi',
                'subject' => '{{firma_adi}} — Araç bakıma çekildi: {{plaka}}',
                'body' => <<<'MD'
# Araç Bakıma Çekildi

- **Araç:** {{plaka}} — {{marka_model}}
- **Bloklayan:** {{blocked_by}}
- **Blok Tarihi:** {{blocked_at}}

{{uygulama_adi}}
MD,
            ],
            [
                'key' => 'fleet_vehicle_unblocked',
                'name' => 'Filo — Bakım Kaydı Eklendi',
                'subject' => '{{firma_adi}} — Bakım kaydı eklendi: {{plaka}}',
                'body' => <<<'MD'
# Bakım Kaydı Eklendi

- **Araç:** {{plaka}} — {{marka_model}}
- **İşlem Türü:** {{islem_turu}}
- **Yapılan İşlemler:** {{yapilan_islemler}}
- **Yeni Bakım Tarihi:** {{yeni_bakim_tarihi}}
- **Yeni Muayene Tarihi:** {{yeni_muayene_tarihi}}

{{uygulama_adi}}
MD,
            ],
            [
                'key' => 'fleet_vehicle_critical_window',
                'name' => 'Filo — Bakım/Muayene Kritik Pencere Uyarısı',
                'subject' => '{{firma_adi}} — {{tur}} uyarısı: {{plaka}} ({{kalan_gun}} gün kaldı)',
                'body' => <<<'MD'
# Kritik Pencere Uyarısı

- **Araç:** {{plaka}} — {{marka_model}}
- **İşlem Türü:** {{tur}}
- **Hedef Tarih:** {{hedef_tarih}}
- **Kalan Gün:** {{kalan_gun}}

Lütfen bakım/muayene randevusunu planlayın.

{{uygulama_adi}}
MD,
            ],
            [
                'key' => 'fleet_vehicle_mtv_30_days',
                'name' => 'Filo — MTV 30 Gün Uyarısı',
                'subject' => '{{firma_adi}} — MTV uyarısı: {{plaka}} ({{kalan_gun}} gün kaldı)',
                'body' => <<<'MD'
# MTV Ödeme Uyarısı

- **Araç:** {{plaka}} — {{marka_model}}
- **MTV Tarihi:** {{mtv_tarih}}
- **Kalan Gün:** {{kalan_gun}}

Ödemenin son gününden önce yapılması önerilir.

{{uygulama_adi}}
MD,
            ],
            [
                'key' => 'fleet_vehicle_mtv_overdue',
                'name' => 'Filo — MTV Gecikti',
                'subject' => '{{firma_adi}} — MTV GECİKTİ: {{plaka}} ({{gecikme_gun}} gün)',
                'body' => <<<'MD'
# MTV Ödemesi Geçmişte Kaldı

- **Araç:** {{plaka}} — {{marka_model}}
- **MTV Tarihi:** {{mtv_tarih}}
- **Gecikme (gün):** {{gecikme_gun}}

Cezalı ödeme oluşabilir; lütfen bir an önce işleme alın.

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
