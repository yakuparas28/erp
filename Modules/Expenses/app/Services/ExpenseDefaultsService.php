<?php

namespace Modules\Expenses\Services;

use App\Models\Tenant;
use Modules\Expenses\Models\ExpenseCategory;

/**
 * Yeni tenant'a Expenses aktif edildiğinde çağrılır; standart Türkçe
 * kategori kataloğunu yaratır (yemek, ulaşım, günlük harcırah, konaklama,
 * kırtasiye, telefon/internet, kilometre, temsil).
 */
class ExpenseDefaultsService
{
    public function provision(Tenant $tenant): void
    {
        // Kod alanı sadece hesap planında karşılığı olan bir kod olmalı (770 = Genel Yönetim Giderleri).
        // Alt-kırılım hesaplar hesap planına eklendikçe bu değerler güncellenebilir.
        $catalog = [
            ['YEM', 'Yemek', 0, 'Adet', '770'],
            ['ULS', 'Ulaşım', 0, 'Adet', '770'],
            ['KON', 'Konaklama', 0, 'Adet', '770'],
            ['HAR', 'Günlük Harcırah', 500, 'Gün', '770'],
            ['KM', 'Kilometre', 4, 'Km', '770'],
            ['KIR', 'Kırtasiye', 0, 'Adet', '770'],
            ['TEL', 'Telefon / İnternet', 0, 'Adet', '770'],
            ['TEM', 'Temsil & Ağırlama', 0, 'Adet', '770'],
        ];

        foreach ($catalog as [$code, $name, $unitPrice, $unitLabel, $account]) {
            ExpenseCategory::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $code],
                [
                    'name' => $name,
                    'unit_price' => $unitPrice,
                    'unit_label' => $unitLabel,
                    'expense_account_code' => $account,
                    'is_reinvoiceable' => false,
                    'is_active' => true,
                ],
            );
        }
    }
}
