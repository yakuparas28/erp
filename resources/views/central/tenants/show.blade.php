@extends('central.layouts.app')

@section('title', $tenant->name)

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <div>
        <h1 class="text-gray-900 text-xl font-bold mb-1">{{ $tenant->name }}</h1>
        <p class="text-sm text-default mb-0">
            {{ __('Accounting mode') }}: {{ $tenant->accounting_mode === 'anglo_saxon' ? __('Anglo-Saxon') : __('Türkiye (Continental)') }}
            &middot; {{ __('Registered') }}: {{ $tenant->created_at->format('d.m.Y') }}
        </p>
    </div>
    <a href="{{ route('central.web.tenants.index') }}" class="btn-sm bg-white border border-border-color text-gray-900 inline-flex items-center gap-2 hover:bg-light">
        <i class="ph ph-arrow-left"></i> {{ __('Back to List') }}
    </a>
</div>

<div class="bg-white border border-border-color rounded-md p-4 mb-4">
    <h2 class="text-base font-bold text-title mb-3">{{ __('Company Information') }}</h2>
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 text-sm">
        <div><p class="text-[11px] text-default mb-0.5">{{ __('Tax Number') }}</p><p class="text-title mb-0">{{ $tenant->tax_number ?? '—' }}</p></div>
        <div><p class="text-[11px] text-default mb-0.5">{{ __('Tax Office') }}</p><p class="text-title mb-0">{{ $tenant->tax_office ?? '—' }}</p></div>
        <div><p class="text-[11px] text-default mb-0.5">{{ __('Email') }}</p><p class="text-title mb-0">{{ $tenant->email ?? '—' }}</p></div>
        <div><p class="text-[11px] text-default mb-0.5">{{ __('Phone') }}</p><p class="text-title mb-0">{{ $tenant->phone ?? '—' }}</p></div>
        <div><p class="text-[11px] text-default mb-0.5">{{ __('Address') }}</p><p class="text-title mb-0">{{ $tenant->address ?? '—' }}</p></div>
    </div>
</div>

<div class="grid grid-cols-12 gap-4">
    {{-- Abonelik --}}
    <div class="col-span-12 lg:col-span-5">
        <div class="bg-white border border-border-color rounded-md p-4">
            <h2 class="text-base font-bold text-title mb-3">{{ __('License Package') }}</h2>
            @if ($subscription)
                <p class="text-sm text-default mb-3">
                    {{ __('Current') }}: <span class="font-semibold text-title">{{ $subscription->licensePackage->name }}</span>
                    @if ($subscription->ends_at)
                        <span class="text-[11px] {{ $subscription->ends_at->isPast() ? 'bg-danger-transparent text-danger' : 'bg-info-transparent text-info' }} px-2 py-0.5 rounded ms-1">
                            {{ $subscription->ends_at->isPast() ? __('Expired').': ' : __('Ends').': ' }}{{ $subscription->ends_at->format('d.m.Y') }}
                        </span>
                    @endif
                    <span class="text-[11px] {{ $subscription->status === 'active' ? 'bg-success-transparent text-success' : 'bg-warning-transparent text-warning' }} px-2 py-0.5 rounded ms-1">
                        {{ ['trial' => __('Trial'), 'active' => __('Active'), 'past_due' => __('Past Due'), 'cancelled' => __('Cancelled')][$subscription->status] }}
                    </span>
                </p>
            @endif
            <form method="POST" action="{{ route('central.web.tenants.subscription.store', $tenant) }}" class="space-y-3">
                @csrf
                <div>
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Package') }}</label>
                    <select name="license_package_id" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                        @foreach ($packages as $package)
                            <option value="{{ $package->id }}" @selected($subscription?->license_package_id === $package->id)>
                                {{ $package->name }} — ₺{{ number_format((float) $package->monthly_price, 0, ',', '.') }}/{{ __('mo') }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Status') }}</label>
                        <select name="status" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                            <option value="trial" @selected($subscription?->status === 'trial')>{{ __('Trial') }}</option>
                            <option value="active" @selected(($subscription?->status ?? 'active') === 'active')>{{ __('Active') }}</option>
                            <option value="past_due" @selected($subscription?->status === 'past_due')>{{ __('Past Due') }}</option>
                            <option value="cancelled" @selected($subscription?->status === 'cancelled')>{{ __('Cancelled') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Start Date') }}</label>
                        <input type="date" name="starts_at" required value="{{ $subscription?->starts_at?->toDateString() ?? now()->toDateString() }}"
                               class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                    </div>
                </div>
                <div>
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('End Date') }}</label>
                    <input type="date" name="ends_at" value="{{ $subscription?->ends_at?->toDateString() }}"
                           class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                    <p class="text-[11px] text-default mt-1 mb-0">{{ __('Leave empty for unlimited. Package modules of expired subscriptions are deactivated automatically every night.') }}</p>
                </div>
                <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">
                    {{ __('Save Subscription') }}
                </button>
            </form>
        </div>

        <div class="bg-white border border-border-color rounded-md p-4 mt-4">
            <h2 class="text-base font-bold text-title mb-1">{{ __('Email (SMTP) Settings') }}</h2>
            <p class="text-[11px] text-default mb-3">{{ __("This tenant's notifications are sent from its own SMTP server; the platform setting is used if not defined.") }}</p>
            <form method="POST" action="{{ route('central.web.tenants.mail-settings.update', $tenant) }}">
                @csrf
                @method('PUT')
                @include('central.settings._mail-form', ['setting' => $mailSetting, 'formId' => 'tenant'])
                <div class="mt-3">
                    <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Save SMTP Settings') }}</button>
                </div>
            </form>
        </div>

        <div class="bg-white border border-border-color rounded-md p-4 mt-4">
            <h2 class="text-base font-bold text-title mb-3">{{ __('Recent Activity') }}</h2>
            <ul class="space-y-2">
                @forelse ($activities as $activity)
                    <li class="text-sm text-default flex items-center justify-between gap-2">
                        <span>{{ $activity->description }}</span>
                        <span class="text-[11px]">{{ $activity->created_at->format('d.m.Y H:i') }}</span>
                    </li>
                @empty
                    <li class="text-sm text-default">{{ __('No records.') }}</li>
                @endforelse
            </ul>
        </div>
    </div>

    {{-- Modüller --}}
    <div class="col-span-12 lg:col-span-7">
        <div class="bg-white border border-border-color rounded-md p-4">
            <h2 class="text-base font-bold text-title mb-3">{{ __('Modules') }}</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-sm text-default border-b border-border-color">
                            <th class="text-left py-2 px-2 font-semibold text-gray-900">{{ __('Module') }}</th>
                            <th class="text-left py-2 px-2 font-semibold text-gray-900">{{ __('Status') }}</th>
                            <th class="text-left py-2 px-2 font-semibold text-gray-900">{{ __('Source') }}</th>
                            <th class="text-left py-2 px-2 font-semibold text-gray-900">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($modules as $module)
                            @php($activation = $activations->get($module->id))
                            @php($isActive = $module->is_core || ($activation?->is_active ?? false))
                            <tr class="border-b border-border-color">
                                <td class="py-2.5 px-2">
                                    <span class="text-sm font-semibold text-title">{{ $module->name }}</span>
                                    @if ($module->is_core)
                                        <span class="text-[11px] bg-info-transparent text-info px-2 py-0.5 rounded ms-1">{{ __('Core') }}</span>
                                    @endif
                                </td>
                                <td class="py-2.5 px-2">
                                    <span class="text-[11px] {{ $isActive ? 'bg-success-transparent text-success' : 'bg-light text-default' }} px-2 py-0.5 rounded">
                                        {{ $isActive ? __('Active') : __('Inactive') }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-2 text-sm text-default">
                                    @if ($module->is_core)
                                        {{ __('Always active') }}
                                    @elseif ($activation)
                                        {{ ['package' => __('Package'), 'manual_addon' => __('Manual Add-on'), 'trial' => __('Trial')][$activation->source] }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="py-2.5 px-2">
                                    @if ($module->is_core)
                                        <span class="text-[11px] text-default">{{ __('Cannot be disabled') }}</span>
                                    @elseif ($isActive)
                                        <form method="POST" action="{{ route('central.web.tenants.modules.deactivate', [$tenant, $module->key]) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-sm bg-white border border-danger text-danger hover:bg-danger hover:text-white cursor-pointer">
                                                {{ __('Deactivate') }}
                                            </button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('central.web.tenants.modules.activate', [$tenant, $module->key]) }}">
                                            @csrf
                                            <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">
                                                {{ __('Activate (Manual)') }}
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white border border-border-color rounded-md p-4 mt-4">
            <h2 class="text-base font-bold text-title mb-3">{{ __('Users') }}</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-sm text-default border-b border-border-color">
                            <th class="text-left py-2 px-2 font-semibold text-gray-900">{{ __('Name') }}</th>
                            <th class="text-left py-2 px-2 font-semibold text-gray-900">{{ __('Email') }}</th>
                            <th class="text-left py-2 px-2 font-semibold text-gray-900">{{ __('Role') }}</th>
                            <th class="text-left py-2 px-2 font-semibold text-gray-900">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $user)
                            <tr class="border-b border-border-color">
                                <td class="py-2.5 px-2 text-sm font-semibold text-title">{{ $user->name }}</td>
                                <td class="py-2.5 px-2 text-sm text-default">{{ $user->email }}</td>
                                <td class="py-2.5 px-2 text-sm text-default">{{ $user->getRoleNames()->join(', ') ?: '—' }}</td>
                                <td class="py-2.5 px-2">
                                    <form method="POST" action="{{ route('central.web.tenants.users.impersonate', [$tenant, $user]) }}">
                                        @csrf
                                        <button type="submit" class="btn-sm bg-white border border-border-color text-gray-900 inline-flex items-center gap-2 hover:bg-light cursor-pointer">
                                            <i class="ph ph-sign-in"></i> {{ __('Login as this user') }}
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-6 text-center text-sm text-default">{{ __('No users.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
