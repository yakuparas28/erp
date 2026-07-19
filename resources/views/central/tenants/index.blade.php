@extends('central.layouts.app')

@section('title', "Tenant'lar")

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <h1 class="text-gray-900 text-xl font-bold mb-0">Tenant'lar</h1>
    <button type="button" data-hs-overlay="#add-tenant-modal"
            class="btn-sm bg-dark text-white border border-dark inline-flex items-center gap-2 hover:bg-primary-hover hover:border-primary-hover cursor-pointer">
        <i class="ph ph-plus"></i> Yeni Tenant
    </button>
</div>

<div class="bg-white border border-border-color rounded-md">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-sm text-default border-b border-border-color">
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">Firma</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">Muhasebe Modu</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">Paket</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">Abonelik</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">Kullanıcı</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">Oluşturma</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">İşlem</th>
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
                            {{ $tenant->accounting_mode === 'anglo_saxon' ? 'Anglo-Sakson' : 'Türkiye (Kıta Avrupası)' }}
                        </td>
                        <td class="py-2.5 px-3 text-sm text-default">
                            {{ $subscription?->licensePackage?->name ?? '—' }}
                        </td>
                        <td class="py-2.5 px-3">
                            @if ($subscription)
                                <span class="text-[11px] {{ $subscription->status === 'active' ? 'bg-success-transparent text-success' : 'bg-warning-transparent text-warning' }} px-2 py-0.5 rounded">
                                    {{ ['trial' => 'Deneme', 'active' => 'Aktif', 'past_due' => 'Gecikmiş', 'cancelled' => 'İptal'][$subscription->status] }}
                                </span>
                            @else
                                <span class="text-[11px] bg-light text-default px-2 py-0.5 rounded">Abonelik yok</span>
                            @endif
                        </td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $tenant->users->count() }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $tenant->created_at->format('d.m.Y') }}</td>
                        <td class="py-2.5 px-3">
                            <div class="flex items-center gap-2">
                                <a href="{{ route('central.web.tenants.show', $tenant) }}"
                                   class="size-7 rounded-md border border-border-color flex items-center justify-center text-default hover:bg-light" title="Detay">
                                    <i class="ph ph-eye"></i>
                                </a>
                                <button type="button" data-hs-overlay="#edit-tenant-modal-{{ $tenant->id }}"
                                        class="size-7 rounded-md border border-border-color flex items-center justify-center text-default hover:bg-light cursor-pointer" title="Düzenle">
                                    <i class="ph ph-pencil-simple-line"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="py-8 text-center text-sm text-default">Henüz tenant yok. "Yeni Tenant" ile ekleyin.</td>
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
                <h2 class="text-base font-bold text-title">Yeni Tenant</h2>
                <button type="button" class="size-7 inline-flex justify-center items-center rounded-md border border-border-color hover:bg-light cursor-pointer" data-hs-overlay="#add-tenant-modal" aria-label="kapat">
                    <i class="ph ph-x text-sm"></i>
                </button>
            </div>
            <div class="p-4 grid grid-cols-12 gap-3">
                <div class="col-span-12">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">Firma Adı <span class="text-danger">*</span></label>
                    <input type="text" name="name" required value="{{ old('name') }}"
                           class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                </div>
                <div class="col-span-12">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">Muhasebe Modu</label>
                    <select name="accounting_mode" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                        <option value="continental">Türkiye — Kıta Avrupası (Tekdüzen Hesap Planı)</option>
                        <option value="anglo_saxon">Anglo-Sakson (Uluslararası)</option>
                    </select>
                    <p class="text-[11px] text-default mt-1 mb-0">Türkiye'de faaliyet gösteren firmalar için "Türkiye — Kıta Avrupası" seçilmelidir; KDV, Tekdüzen Hesap Planı ve e-Fatura uyumu bu modda çalışır.</p>
                </div>
            </div>
            <div class="flex justify-end gap-2 p-4 border-t border-border-color">
                <button type="button" class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light cursor-pointer" data-hs-overlay="#add-tenant-modal">Vazgeç</button>
                <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">Kaydet</button>
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
                    <h2 class="text-base font-bold text-title">Tenant Düzenle</h2>
                    <button type="button" class="size-7 inline-flex justify-center items-center rounded-md border border-border-color hover:bg-light cursor-pointer" data-hs-overlay="#edit-tenant-modal-{{ $tenant->id }}" aria-label="kapat">
                        <i class="ph ph-x text-sm"></i>
                    </button>
                </div>
                <div class="p-4 grid grid-cols-12 gap-3">
                    <div class="col-span-12">
                        <label class="text-sm font-semibold text-gray-900 mb-1 block">Firma Adı <span class="text-danger">*</span></label>
                        <input type="text" name="name" required value="{{ $tenant->name }}"
                               class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                    </div>
                    <div class="col-span-12">
                        <label class="text-sm font-semibold text-gray-900 mb-1 block">Muhasebe Modu</label>
                        <select name="accounting_mode" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                            <option value="continental" @selected($tenant->accounting_mode === 'continental')>Türkiye — Kıta Avrupası (Tekdüzen Hesap Planı)</option>
                            <option value="anglo_saxon" @selected($tenant->accounting_mode === 'anglo_saxon')>Anglo-Sakson (Uluslararası)</option>
                        </select>
                    </div>
                </div>
                <div class="flex justify-end gap-2 p-4 border-t border-border-color">
                    <button type="button" class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light cursor-pointer" data-hs-overlay="#edit-tenant-modal-{{ $tenant->id }}">Vazgeç</button>
                    <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">Güncelle</button>
                </div>
            </form>
        </div>
    </div>
@endforeach
@endsection
