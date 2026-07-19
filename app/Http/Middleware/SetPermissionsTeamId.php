<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Spatie Permission team bağlamını, kimliği doğrulanmış kullanıcının
 * tenant_id'sine sabitler. SuperAdmin'de tenant_id olmadığından team
 * bağlamı platform istekleri için hiç kurulmaz.
 */
class SetPermissionsTeamId
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->hasUser() && auth()->user()->getAttribute('tenant_id') !== null) {
            setPermissionsTeamId(auth()->user()->getAttribute('tenant_id'));
        }

        return $next($request);
    }
}
