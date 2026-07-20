@extends('central.layouts.app')

@section('title', __('Tenants'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <h1 class="text-gray-900 text-xl font-bold mb-0">{{ __('Tenants') }}</h1>
    <button type="button" data-hs-overlay="#add-tenant-modal"
            class="btn-sm bg-dark text-white border border-dark inline-flex items-center gap-2 hover:bg-primary-hover hover:border-primary-hover cursor-pointer">
        <i class="ph ph-plus"></i> {{ __('New Tenant') }}
    </button>
</div>

<div class="bg-white border border-border-color rounded-md">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-sm text-default border-b border-border-color">
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Company') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Accounting Mode') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Package') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Subscription') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Users') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Created') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($tenants as $tenant)
                    @php($subscription = $subscriptions->get($tenant->id))
                    <tr class="border-b border-border-color">
                        <td class="py-2.5 px-3">
                            <a href="{{ route('central.web.tenants.show', $tenant) }}" class="text-sm font-semibold text-title hover:underline">
                                {{ $tenant->name }}
                            </a>
                        </td>
                        <td class="py-2.5 px-3 text-sm text-default">
                            {{ $tenant->accounting_mode === 'anglo_saxon' ? __('Anglo-Saxon') : __('Türkiye (Continental)') }}
                        </td>
                        <td class="py-2.5 px-3 text-sm text-default">
                            {{ $subscription?->licensePackage?->name ?? '—' }}
                        </td>
                        <td class="py-2.5 px-3">
                            @if ($subscription)
                                <span class="text-[11px] {{ $subscription->status === 'active' ? 'bg-success-transparent text-success' : 'bg-warning-transparent text-warning' }} px-2 py-0.5 rounded">
                                    {{ ['trial' => __('Trial'), 'active' => __('Active'), 'past_due' => __('Past Due'), 'cancelled' => __('Cancelled')][$subscription->status] }}
                                </span>
                            @else
                                <span class="text-[11px] bg-light text-default px-2 py-0.5 rounded">{{ __('No subscription') }}</span>
                            @endif
                        </td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $tenant->users->count() }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $tenant->created_at->format('d.m.Y') }}</td>
                        <td class="py-2.5 px-3">
                            <div class="flex items-center gap-2">
                                <a href="{{ route('central.web.tenants.show', $tenant) }}"
                                   class="size-7 rounded-md border border-border-color flex items-center justify-center text-default hover:bg-light" title="{{ __('Details') }}">
                                    <i class="ph ph-eye"></i>
                                </a>
                                <button type="button" data-hs-overlay="#edit-tenant-modal-{{ $tenant->id }}"
                                        class="size-7 rounded-md border border-border-color flex items-center justify-center text-default hover:bg-light cursor-pointer" title="{{ __('Edit') }}">
                                    <i class="ph ph-pencil-simple-line"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="py-8 text-center text-sm text-default">{{ __('No tenants yet. Add one with "New Tenant".') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Yeni tenant modal --}}
<div id="add-tenant-modal" class="hs-overlay hidden size-full fixed top-0 start-0 z-[80] overflow-x-hidden overflow-y-auto pointer-events-none">
    <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-xl sm:w-full m-3 sm:mx-auto pointer-events-auto min-h-screen flex items-center justify-center">
        <form method="POST" action="{{ route('central.web.tenants.store') }}" class="flex flex-col bg-white border shadow-sm rounded-md border-border-color w-full">
            @csrf
            <div class="flex justify-between items-center p-4 border-b border-border-color">
                <h2 class="text-base font-bold text-title">{{ __('New Tenant') }}</h2>
                <button type="button" class="size-7 inline-flex justify-center items-center rounded-md border border-border-color hover:bg-light cursor-pointer" data-hs-overlay="#add-tenant-modal" aria-label="kapat">
                    <i class="ph ph-x text-sm"></i>
                </button>
            </div>
            <div class="p-4 grid grid-cols-12 gap-3">
                <div class="col-span-12">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Company Name') }} <span class="text-danger">*</span></label>
                    <input type="text" name="name" required value="{{ old('name') }}"
                           class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                </div>
                <div class="col-span-12">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Accounting Mode') }}</label>
                    <select name="accounting_mode" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                        <option value="continental">{{ __('Türkiye — Continental (Uniform Chart of Accounts)') }}</option>
                        <option value="anglo_saxon">{{ __('Anglo-Saxon (International)') }}</option>
                    </select>
                    <p class="text-[11px] text-default mt-1 mb-0">{{ __('Companies operating in Türkiye should select "Türkiye — Continental"; VAT, the Uniform Chart of Accounts and e-Invoice compliance work in this mode.') }}</p>
                </div>
                <div class="col-span-12 sm:col-span-6">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Tax Number') }}</label>
                    <input type="text" name="tax_number" value="{{ old('tax_number') }}"
                           class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                </div>
                <div class="col-span-12 sm:col-span-6">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Tax Office') }}</label>
                    <input type="text" name="tax_office" value="{{ old('tax_office') }}"
                           class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                </div>
                <div class="col-span-12 sm:col-span-6">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Company Email') }}</label>
                    <input type="email" name="email" value="{{ old('email') }}"
                           class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                </div>
                <div class="col-span-12 sm:col-span-6">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Phone') }}</label>
                    <input type="text" name="phone" value="{{ old('phone') }}"
                           class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                </div>
                <div class="col-span-12">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Address') }}</label>
                    <input type="text" name="address" value="{{ old('address') }}"
                           class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                </div>
                <div class="col-span-12 border-t border-border-color pt-3 mt-1">
                    <h3 class="text-sm font-bold text-title mb-2">{{ __('Tenant Administrator') }}</h3>
                    <p class="text-[11px] text-default mb-2">{{ __('An administrator account is created for the company; credentials are sent by email.') }}</p>
                </div>
                <div class="col-span-12 sm:col-span-6">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Administrator Name') }} <span class="text-danger">*</span></label>
                    <input type="text" name="admin_name" required value="{{ old('admin_name') }}"
                           class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                </div>
                <div class="col-span-12 sm:col-span-6">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Administrator Email') }} <span class="text-danger">*</span></label>
                    <input type="email" name="admin_email" required value="{{ old('admin_email') }}"
                           class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                </div>
            </div>
            <div class="flex justify-end gap-2 p-4 border-t border-border-color">
                <button type="button" class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light cursor-pointer" data-hs-overlay="#add-tenant-modal">{{ __('Cancel') }}</button>
                <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Save') }}</button>
            </div>
        </form>
    </div>
</div>

{{-- Düzenleme modalları --}}
@foreach ($tenants as $tenant)
    <div id="edit-tenant-modal-{{ $tenant->id }}" class="hs-overlay hidden size-full fixed top-0 start-0 z-[80] overflow-x-hidden overflow-y-auto pointer-events-none">
        <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-xl sm:w-full m-3 sm:mx-auto pointer-events-auto min-h-screen flex items-center justify-center">
            <form method="POST" action="{{ route('central.web.tenants.update', $tenant) }}" class="flex flex-col bg-white border shadow-sm rounded-md border-border-color w-full">
                @csrf
                @method('PUT')
                <div class="flex justify-between items-center p-4 border-b border-border-color">
                    <h2 class="text-base font-bold text-title">{{ __('Edit Tenant') }}</h2>
                    <button type="button" class="size-7 inline-flex justify-center items-center rounded-md border border-border-color hover:bg-light cursor-pointer" data-hs-overlay="#edit-tenant-modal-{{ $tenant->id }}" aria-label="kapat">
                        <i class="ph ph-x text-sm"></i>
                    </button>
                </div>
                <div class="p-4 grid grid-cols-12 gap-3">
                    <div class="col-span-12">
                        <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Company Name') }} <span class="text-danger">*</span></label>
                        <input type="text" name="name" required value="{{ $tenant->name }}"
                               class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                    </div>
                    <div class="col-span-12">
                        <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Accounting Mode') }}</label>
                        <select name="accounting_mode" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                            <option value="continental" @selected($tenant->accounting_mode === 'continental')>{{ __('Türkiye — Continental (Uniform Chart of Accounts)') }}</option>
                            <option value="anglo_saxon" @selected($tenant->accounting_mode === 'anglo_saxon')>{{ __('Anglo-Saxon (International)') }}</option>
                        </select>
                    </div>
                </div>
                <div class="flex justify-end gap-2 p-4 border-t border-border-color">
                    <button type="button" class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light cursor-pointer" data-hs-overlay="#edit-tenant-modal-{{ $tenant->id }}">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Update') }}</button>
                </div>
            </form>
        </div>
    </div>
@endforeach
@endsection
