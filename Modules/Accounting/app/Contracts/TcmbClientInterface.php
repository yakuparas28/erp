<?php

namespace Modules\Accounting\Contracts;

/**
 * TCMB kur servisiyle iletişim sözleşmesi (Strategy — CostingStrategyInterface
 * ile aynı desende). Gerçek implementasyon TCMB'nin günlük kur.xml'ini okur;
 * testlerde bu arayüz sahtelenir.
 */
interface TcmbClientInterface
{
    /**
     * @return array<string, array{buy: string, sell: string}> — currency code => rates
     */
    public function fetchRates(string $date): array;
}
