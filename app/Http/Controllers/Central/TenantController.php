<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\ProvisionTenantRequest;
use App\Http\Requests\Central\StoreSubscriptionRequest;
use App\Http\Requests\Central\StoreTenantRequest;
use App\Models\LicensePackage;
use App\Models\MailSetting;
use App\Models\Module;
use App\Models\Tenant;
use App\Models\TenantModuleActivation;
use App\Models\TenantSubscription;
use App\Services\Platform\ModuleActivationService;
use App\Services\Platform\TenantProvisioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use Spatie\Activitylog\Models\Activity;

class TenantController extends Controller
{
    public function __construct(
        private readonly ModuleActivationService $activations,
        private readonly TenantProvisioningService $provisioning,
    ) {}

    public function index(): View
    {
        return view('central.tenants.index', [
            'tenants' => Tenant::with('users')->latest()->get(),
            'subscriptions' => TenantSubscription::with('licensePackage')->get()->keyBy('tenant_id'),
        ]);
    }

    public function store(ProvisionTenantRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $tenant = $this->provisioning->createWithAdmin(
            collect($validated)->except(['admin_name', 'admin_email'])->all(),
            $validated['admin_name'],
            $validated['admin_email'],
        );

        activity()
            ->causedBy($request->user('central_web'))
            ->performedOn($tenant)
            ->withProperties(['admin_email' => $validated['admin_email']])
            ->log('tenant.created');

        return redirect()
            ->route('central.web.tenants.index')
            ->with('status', "{$tenant->name} oluşturuldu; yönetici bilgileri {$validated['admin_email']} adresine gönderildi.");
    }

    public function show(Tenant $tenant): View
    {
        $activations = TenantModuleActivation::where('tenant_id', $tenant->id)->get()->keyBy('module_id');

        setPermissionsTeamId($tenant->id);

        return view('central.tenants.show', [
            'tenant' => $tenant,
            'subscription' => TenantSubscription::with('licensePackage')->where('tenant_id', $tenant->id)->first(),
            'packages' => LicensePackage::orderBy('monthly_price')->get(),
            'modules' => Module::orderByDesc('is_core')->orderBy('name')->get(),
            'activations' => $activations,
            'mailSetting' => MailSetting::where('tenant_id', $tenant->id)->first(),
            'users' => $tenant->users()->with('roles')->orderBy('name')->get(),
            'activities' => Activity::where(function ($query) use ($tenant): void {
                $query->where('subject_type', 'tenant')->where('subject_id', $tenant->id);
            })->latest()->limit(10)->get(),
        ]);
    }

    public function update(StoreTenantRequest $request, Tenant $tenant): RedirectResponse
    {
        $tenant->update($request->validated());

        activity()
            ->causedBy($request->user('central_web'))
            ->performedOn($tenant)
            ->log('tenant.updated');

        return redirect()
            ->route('central.web.tenants.index')
            ->with('status', "{$tenant->name} güncellendi.");
    }

    public function storeSubscription(StoreSubscriptionRequest $request, Tenant $tenant): RedirectResponse
    {
        $subscription = TenantSubscription::updateOrCreate(
            ['tenant_id' => $tenant->id],
            $request->validated(),
        );

        activity()
            ->causedBy($request->user('central_web'))
            ->performedOn($subscription)
            ->withProperties(['license_package_id' => $subscription->license_package_id])
            ->log('subscription.assigned');

        return redirect()
            ->route('central.web.tenants.show', $tenant)
            ->with('status', 'Abonelik güncellendi, modüller senkronlandı.');
    }

    public function activateModule(Request $request, Tenant $tenant, Module $module): RedirectResponse
    {
        $activation = $this->activations->activateManually($tenant, $module, $request->user('central_web'));

        activity()
            ->causedBy($request->user('central_web'))
            ->performedOn($activation)
            ->withProperties(['module' => $module->key, 'source' => 'manual_addon'])
            ->log('module.activated');

        return redirect()
            ->route('central.web.tenants.show', $tenant)
            ->with('status', "{$module->name} modülü aktive edildi.");
    }

    public function deactivateModule(Request $request, Tenant $tenant, Module $module): RedirectResponse
    {
        try {
            $this->activations->deactivate($tenant, $module, $request->user('central_web'));
        } catch (InvalidArgumentException $exception) {
            return redirect()
                ->route('central.web.tenants.show', $tenant)
                ->withErrors(['module' => $exception->getMessage()]);
        }

        activity()
            ->causedBy($request->user('central_web'))
            ->withProperties(['module' => $module->key, 'tenant_id' => $tenant->id])
            ->log('module.deactivated');

        return redirect()
            ->route('central.web.tenants.show', $tenant)
            ->with('status', "{$module->name} modülü devre dışı bırakıldı.");
    }
}
