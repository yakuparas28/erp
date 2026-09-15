<?php

namespace Database\Seeders;

use App\Models\Module;
use Illuminate\Database\Seeder;

class ModuleSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            ['key' => 'inventory', 'name' => 'Envanter', 'description' => 'Depo, lokasyon, stok sayımı ve maliyetlendirme', 'is_core' => true],
            ['key' => 'sales', 'name' => 'Satış', 'description' => 'Satış siparişleri, rezervasyon ve teslimat', 'is_core' => false],
            ['key' => 'purchase', 'name' => 'Satınalma', 'description' => 'RFQ/PO, mal kabul ve yeniden sipariş', 'is_core' => false],
            ['key' => 'accounting', 'name' => 'Muhasebe', 'description' => 'Hesap planı, yevmiye, fatura ve ödemeler', 'is_core' => false],
            ['key' => 'hr', 'name' => 'İnsan Kaynakları', 'description' => 'Personel, departman, izin yönetimi', 'is_core' => false],
            ['key' => 'fleet', 'name' => 'Filo Yönetimi', 'description' => 'Araç envanteri, rezervasyon, bakım/muayene ve MTV takibi', 'is_core' => false],
            ['key' => 'expenses', 'name' => 'Masraf Yönetimi', 'description' => 'Personel masraf kayıtları, onay akışı ve muhasebeye postalama', 'is_core' => false],
        ];

        foreach ($modules as $attributes) {
            Module::updateOrCreate(['key' => $attributes['key']], $attributes);
        }
    }
}
