<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImpersonationController extends Controller
{
    /**
     * Süper Admin, tenant kullanıcısı olarak oturum açar (web guard).
     * central_web oturumu açık kalır; tenant panelinde uyarı şeridi görünür.
     */
    public function store(Request $request, Tenant $tenant, User $user): RedirectResponse
    {
        abort_unless($user->tenant_id === $tenant->id, 404);

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        activity()
            ->causedBy($request->user('central_web'))
            ->performedOn($user)
            ->withProperties(['tenant_id' => $tenant->id])
            ->log('user.impersonated');

        return redirect()
            ->route('app.dashboard')
            ->with('status', __("You are viewing :name's account.", ['name' => $user->name]));
    }

    /**
     * Impersonation'dan çıkış: yalnızca web guard'ı kapatılır,
     * Süper Admin oturumu sürer.
     */
    public function destroy(Request $request): RedirectResponse
    {
        abort_unless(Auth::guard('central_web')->check(), 403);

        $impersonatedUser = Auth::guard('web')->user();

        Auth::guard('web')->logout();
        $request->session()->regenerateToken();

        return $impersonatedUser?->tenant_id !== null
            ? redirect()->route('central.web.tenants.show', $impersonatedUser->tenant_id)
            : redirect()->route('central.web.tenants.index');
    }
}
