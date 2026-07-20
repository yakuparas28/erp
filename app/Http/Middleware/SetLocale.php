<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Oturumdaki dil tercihini uygular (tr|en). Tercih yoksa app.locale geçerlidir.
 */
class SetLocale
{
    public const SUPPORTED = ['tr', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('locale');

        if (is_string($locale) && in_array($locale, self::SUPPORTED, true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
