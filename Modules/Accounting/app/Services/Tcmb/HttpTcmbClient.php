<?php

namespace Modules\Accounting\Services\Tcmb;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Modules\Accounting\Contracts\TcmbClientInterface;

class HttpTcmbClient implements TcmbClientInterface
{
    private const TRACKED_CODES = ['USD', 'EUR'];

    public function fetchRates(string $date): array
    {
        $parsed = Carbon::parse($date);
        $url = "https://www.tcmb.gov.tr/kurlar/{$parsed->format('Ym')}/{$parsed->format('dmY')}.xml";

        $response = Http::get($url);

        abort_unless($response->successful(), 422, __('TCMB exchange rates could not be fetched for this date.'));

        $xml = simplexml_load_string($response->body());

        abort_if($xml === false, 422, __('TCMB exchange rate response could not be parsed.'));

        $rates = [];

        foreach ($xml->Currency as $currency) {
            $code = (string) $currency['CurrencyCode'];

            if (in_array($code, self::TRACKED_CODES, true)) {
                $rates[$code] = [
                    'buy' => (string) $currency->ForexBuying,
                    'sell' => (string) $currency->ForexSelling,
                ];
            }
        }

        return $rates;
    }
}
