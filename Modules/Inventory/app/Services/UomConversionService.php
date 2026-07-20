<?php

namespace Modules\Inventory\Services;

use InvalidArgumentException;
use Modules\Inventory\Models\Uom;

class UomConversionService
{
    /**
     * Miktarı birimin kategori referans birimine çevirir (PRD 3.4 birim
     * disiplini: stok kayıtları DAİMA referans birimde). decimal string
     * ile çalışır, float kullanılmaz.
     */
    public function toReference(Uom $uom, string $qty): string
    {
        if ($uom->is_reference) {
            return number_format((float) $qty, 4, '.', '');
        }

        if (bccomp($uom->factor, '0', 6) <= 0) {
            throw new InvalidArgumentException("Birim çevrim katsayısı pozitif olmalı: {$uom->name}");
        }

        return bcmul($qty, $uom->factor, 4);
    }
}
