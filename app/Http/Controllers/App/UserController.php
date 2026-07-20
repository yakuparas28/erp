<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Platform\UserInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct(private readonly UserInvitationService $invitations) {}

    public function index(Request $request): View
    {
        $tenantId = $request->user()->tenant_id;

        return view('app.users.index', [
            'users' => User::where('tenant_id', $tenantId)->with('roles')->orderBy('name')->get(),
            'assignableRoles' => $this->assignableRoleNames($tenantId),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [Rule::in($this->assignableRoleNames($request->user()->tenant_id))],
        ]);

        $user = $this->invitations->invite(
            $request->user()->tenant,
            $validated['name'],
            $validated['email'],
            $validated['roles'],
        );

        activity()->causedBy($request->user())->performedOn($user)->log('user.invited');

        return redirect()
            ->route('app.users.index')
            ->with('status', __(':name invited; credentials were sent to :email.', ['name' => $user->name, 'email' => $user->email]));
    }

    public function update(Request $request, int $userId): RedirectResponse
    {
        $user = $this->findTenantUser($request, $userId);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [Rule::in($this->assignableRoleNames($request->user()->tenant_id))],
        ]);

        setPermissionsTeamId($user->tenant_id);

        if ($this->wouldRemoveLastTenantAdmin($user, $validated['roles'])) {
            return back()->withErrors(['roles' => __('The last Tenant Admin role cannot be removed.')]);
        }

        $user->update(['name' => $validated['name']]);
        $user->syncRoles($validated['roles']);

        activity()->causedBy($request->user())->performedOn($user)->log('user.updated');

        return redirect()->route('app.users.index')->with('status', __(':name updated.', ['name' => $user->name]));
    }

    public function destroy(Request $request, int $userId): RedirectResponse
    {
        $user = $this->findTenantUser($request, $userId);

        if ($user->is($request->user())) {
            return back()->withErrors(['user' => __('You cannot delete your own account.')]);
        }

        $user->delete();

        activity()->causedBy($request->user())->withProperties(['email' => $user->email])->log('user.deleted');

        return redirect()->route('app.users.index')->with('status', __(':name deleted.', ['name' => $user->name]));
    }

    private function findTenantUser(Request $request, int $userId): User
    {
        $user = User::findOrFail($userId);

        abort_unless($user->tenant_id === $request->user()->tenant_id, 404);

        return $user;
    }

    /**
     * @return list<string>
     */
    private function assignableRoleNames(int $tenantId): array
    {
        return Role::where(function ($query) use ($tenantId): void {
            $query->whereNull('tenant_id')->orWhere('tenant_id', $tenantId);
        })->orderBy('name')->pluck('name')->all();
    }

    /**
     * @param  list<string>  $newRoles
     */
    private function wouldRemoveLastTenantAdmin(User $user, array $newRoles): bool
    {
        if (in_array('Tenant Admin', $newRoles, true) || ! $user->hasRole('Tenant Admin')) {
            return false;
        }

        $otherAdmins = User::where('tenant_id', $user->tenant_id)
            ->whereKeyNot($user->id)
            ->get()
            ->filter(fn (User $candidate): bool => $candidate->hasRole('Tenant Admin'));

        return $otherAdmins->isEmpty();
    }
}
