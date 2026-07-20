<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\TenantModuleActivation;
use App\Support\PermissionCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(Request $request): View
    {
        $tenantId = $request->user()->tenant_id;

        $roles = Role::where(function ($query) use ($tenantId): void {
            $query->whereNull('tenant_id')->orWhere('tenant_id', $tenantId);
        })->withCount('users')->orderByRaw('tenant_id is not null')->orderBy('name')->get();

        return view('app.roles.index', [
            'roles' => $roles,
            'permissionGroups' => $this->visiblePermissionGroups($tenantId),
            'rolePermissions' => $roles->mapWithKeys(
                fn (Role $role) => [$role->id => $role->permissions->pluck('name')->all()],
            ),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $exists = Role::where('name', $validated['name'])
            ->where(function ($query) use ($request): void {
                $query->whereNull('tenant_id')->orWhere('tenant_id', $request->user()->tenant_id);
            })->exists();

        if ($exists) {
            return back()->withErrors(['name' => __('A role with this name already exists.')]);
        }

        $role = Role::create(['name' => $validated['name'], 'guard_name' => 'web']);

        activity()->causedBy($request->user())->performedOn($role)->log('role.created');

        return redirect()->route('app.roles.index')->with('status', __('Role ":name" created.', ['name' => $role->name]));
    }

    public function update(Request $request, int $roleId): RedirectResponse
    {
        $role = $this->findCustomRole($request, $roleId);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $role->update(['name' => $validated['name']]);

        activity()->causedBy($request->user())->performedOn($role)->log('role.updated');

        return redirect()->route('app.roles.index')->with('status', __('Role ":name" updated.', ['name' => $role->name]));
    }

    public function syncPermissions(Request $request, int $roleId): RedirectResponse
    {
        $role = $this->findCustomRole($request, $roleId);

        $validated = $request->validate([
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => [Rule::in(PermissionCatalog::all())],
        ]);

        $role->syncPermissions($validated['permissions'] ?? []);

        activity()
            ->causedBy($request->user())
            ->performedOn($role)
            ->withProperties(['permissions' => $validated['permissions'] ?? []])
            ->log('role.permissions_updated');

        return redirect()->route('app.roles.index')->with('status', __('Permissions of ":name" updated.', ['name' => $role->name]));
    }

    public function destroy(Request $request, int $roleId): RedirectResponse
    {
        $role = $this->findCustomRole($request, $roleId);

        if ($role->users()->count() > 0) {
            return back()->withErrors(['role' => __('This role is assigned to users and cannot be deleted.')]);
        }

        $role->delete();

        activity()->causedBy($request->user())->withProperties(['role' => $role->name])->log('role.deleted');

        return redirect()->route('app.roles.index')->with('status', __('Role ":name" deleted.', ['name' => $role->name]));
    }

    /**
     * Sistem rolleri (tenant_id null) globaldir; izin/ad değişikliği tüm
     * tenant'ları etkileyeceğinden 403. Yabancı tenant'ın rolü 404.
     */
    private function findCustomRole(Request $request, int $roleId): Role
    {
        $role = Role::findOrFail($roleId);

        abort_if($role->tenant_id === null, 403, __('System roles cannot be modified.'));
        abort_unless($role->tenant_id === $request->user()->tenant_id, 404);

        return $role;
    }

    /**
     * @return array<string, list<string>>
     */
    private function visiblePermissionGroups(int $tenantId): array
    {
        $activeModuleKeys = Module::where('is_core', true)->pluck('key')
            ->merge(
                TenantModuleActivation::where('tenant_id', $tenantId)
                    ->where('is_active', true)
                    ->join('modules', 'modules.id', '=', 'tenant_module_activations.module_id')
                    ->pluck('modules.key'),
            )
            ->unique();

        return collect(PermissionCatalog::groups())
            ->filter(fn (array $permissions, string $group): bool => $group === PermissionCatalog::CORE_GROUP || $activeModuleKeys->contains($group))
            ->all();
    }
}
