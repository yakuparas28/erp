@extends('central.layouts.app')

@section('title', $tenant->name)

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <div>
        <h1 class="text-gray-900 text-xl font-bold mb-1">{{ $tenant->name }}</h1>
        <p class="text-sm text-default mb-0">
            Muhasebe modu: {{ $tenant->accounting_mode === 'anglo_saxon' ? 'Anglo-Sakson' : 'Türkiye (Kıta Avrupası)' }}
            &middot; Kayıt: {{ $tenant->created_at->format('d.m.Y') }}
        </p>
    </div>
    <a href="{{ route('central.web.tenants.index') }}" class="btn-sm bg-white border border-border-color text-gray-900 inline-flex items-center gap-2 hover:bg-light">
        <i class="ph ph-arrow-left"></i> Listeye Dön
    </a>
</div>

<div class="bg-white border border-border-color rounded-md p-4 mb-4">
    <h2 class="text-base font-bold text-title mb-3">Firma Bilgileri</h2>
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 text-sm">
        <div><p class="text-[11px] text-default mb-0.5">Vergi No</p><p class="text-title mb-0">{{ $tenant->tax_number ?? '—' }}</p></div>
        <div><p class="text-[11px] text-default mb-0.5">Vergi Dairesi</p><p class="text-title mb-0">{{ $tenant->tax_office ?? '—' }}</p></div>
        <div><p class="text-[11px] text-default mb-0.5">E-posta</p><p class="text-title mb-0">{{ $tenant->email ?? '—' }}</p></div>
        <div><p class="text-[11px] text-default mb-0.5">Telefon</p><p class="text-title mb-0">{{ $tenant->phone ?? '—' }}</p></div>
        <div><p class="text-[11px] text-default mb-0.5">Adres</p><p class="text-title mb-0">{{ $tenant->address ?? '—' }}</p></div>
    </div>
</div>

<div class="grid grid-cols-12 gap-4">
    {{-- Abonelik --}}
    <div class="col-span-12 lg:col-span-5">
        <div class="bg-white border border-border-color rounded-md p-4">
            <h2 class="text-base font-bold text-title mb-3">Lisans Paketi</h2>
            @if ($subscription)
                <p class="text-sm text-default mb-3">
                    Mevcut: <span class="font-semibold text-title">{{ $subscription->licensePackage->name }}</span>
                    @if ($subscription->ends_at)
                        <span class="text-[11px] {{ $subscription->ends_at->isPast() ? 'bg-danger-transparent text-danger' : 'bg-info-transparent text-info' }} px-2 py-0.5 rounded ms-1">
                            {{ $subscription->ends_at->isPast() ? 'Süresi doldu: ' : 'Bitiş: ' }}{{ $subscription->ends_at->format('d.m.Y') }}
                        </span>
                    @endif
                    <span class="text-[11px] {{ $subscription->status === 'active' ? 'bg-success-transparent text-success' : 'bg-warning-transparent text-warning' }} px-2 py-0.5 rounded ms-1">
                        {{ ['trial' => 'Deneme', 'active' => 'Aktif', 'past_due' => 'Gecikmiş', 'cancelled' => 'İptal'][$subscription->status] }}
                    </span>
                </p>
            @endif
            <form method="POST" action="{{ route('central.web.tenants.subscription.store', $tenant) }}" class="space-y-3">
                @csrf
                <div>
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">Paket</label>
                    <select name="license_package_id" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                        @foreach ($packages as $package)
                            <option value="{{ $package->id }}" @selected($subscription?->license_package_id === $package->id)>
                                {{ $package->name }} — ₺{{ number_format((float) $package->monthly_price, 0, ',', '.') }}/ay
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-sm font-semibold text-gray-900 mb-1 block">Durum</label>
                        <select name="status" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                            <option value="trial" @selected($subscription?->status === 'trial')>Deneme</option>
                            <option value="active" @selected(($subscription?->status ?? 'active') === 'active')>Aktif</option>
                            <option value="past_due" @selected($subscription?->status === 'past_due')>Gecikmiş</option>
                            <option value="cancelled" @selected($subscription?->status === 'cancelled')>İptal</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-gray-900 mb-1 block">Başlangıç</label>
                        <input type="date" name="starts_at" required value="{{ $subscription?->starts_at?->toDateString() ?? now()->toDateString() }}"
                               class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                    </div>
                </div>
                <div>
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">Bitiş Tarihi</label>
                    <input type="date" name="ends_at" value="{{ $subscription?->ends_at?->toDateString() }}"
                           class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                    <p class="text-[11px] text-default mt-1 mb-0">Boş bırakılırsa süresizdir. Süresi dolan aboneliğin paket modülleri her gece otomatik pasifleştirilir.</p>
                </div>
                <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">
                    Aboneliği Kaydet
                </button>
            </form>
        </div>

        <div class="bg-white border border-border-color rounded-md p-4 mt-4">
            <h2 class="text-base font-bold text-title mb-3">Son İşlemler</h2>
            <ul class="space-y-2">
                @forelse ($activities as $activity)
                    <li class="text-sm text-default flex items-center justify-between gap-2">
                        <span>{{ $activity->description }}</span>
                        <span class="text-[11px]">{{ $activity->created_at->format('d.m.Y H:i') }}</span>
                    </li>
                @empty
                    <li class="text-sm text-default">Kayıt yok.</li>
                @endforelse
            </ul>
        </div>
    </div>

    {{-- Modüller --}}
    <div class="col-span-12 lg:col-span-7">
        <div class="bg-white border border-border-color rounded-md p-4">
            <h2 class="text-base font-bold text-title mb-3">Modüller</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-sm text-default border-b border-border-color">
                            <th class="text-left py-2 px-2 font-semibold text-gray-900">Modül</th>
                            <th class="text-left py-2 px-2 font-semibold text-gray-900">Durum</th>
                            <th class="text-left py-2 px-2 font-semibold text-gray-900">Kaynak</th>
                            <th class="text-left py-2 px-2 font-semibold text-gray-900">İşlem</th>
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
                                        <span class="text-[11px] bg-info-transparent text-info px-2 py-0.5 rounded ms-1">Çekirdek</span>
                                    @endif
                                </td>
                                <td class="py-2.5 px-2">
                                    <span class="text-[11px] {{ $isActive ? 'bg-success-transparent text-success' : 'bg-light text-default' }} px-2 py-0.5 rounded">
                                        {{ $isActive ? 'Aktif' : 'Kapalı' }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-2 text-sm text-default">
                                    @if ($module->is_core)
                                        Her zaman aktif
                                    @elseif ($activation)
                                        {{ ['package' => 'Paket', 'manual_addon' => 'Manuel Eklenti', 'trial' => 'Deneme'][$activation->source] }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="py-2.5 px-2">
                                    @if ($module->is_core)
                                        <span class="text-[11px] text-default">Kapatılamaz</span>
                                    @elseif ($isActive)
                                        <form method="POST" action="{{ route('central.web.tenants.modules.deactivate', [$tenant, $module->key]) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-sm bg-white border border-danger text-danger hover:bg-danger hover:text-white cursor-pointer">
                                                Devre Dışı Bırak
                                            </button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('central.web.tenants.modules.activate', [$tenant, $module->key]) }}">
                                            @csrf
                                            <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">
                                                Aktive Et (Manuel)
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
    </div>
</div>
@endsection
