@extends('app.layouts.app')

@section('title', 'Kontrol Paneli')

@section('content')
<div class="mb-3 lg:mb-6">
    <h1 class="text-gray-900 text-xl font-bold mb-1">Hoş geldiniz, {{ auth()->user()->name }}</h1>
    <p class="text-sm text-default mb-0">
        {{ $tenant->name }}
        @if ($subscription)
            &middot; {{ $subscription->licensePackage->name }} paketi
            @if ($subscription->ends_at)
                &middot; Bitiş: {{ $subscription->ends_at->format('d.m.Y') }}
            @endif
        @endif
    </p>
</div>

<div class="grid grid-cols-12 gap-4">
    @foreach ($modules as $item)
        <div class="col-span-12 sm:col-span-6 lg:col-span-3">
            <div class="bg-white border border-border-color rounded-md p-4 h-full {{ $item['isActive'] ? '' : 'opacity-60' }}">
                <div class="flex items-center justify-between mb-2">
                    <i class="ph-duotone {{ ['inventory' => 'ph-package', 'sales' => 'ph-shopping-cart', 'purchase' => 'ph-truck', 'accounting' => 'ph-calculator'][$item['module']->key] ?? 'ph-cube' }} text-2xl text-title"></i>
                    <span class="text-[11px] {{ $item['isActive'] ? 'bg-success-transparent text-success' : 'bg-light text-default' }} px-2 py-0.5 rounded">
                        {{ $item['isActive'] ? 'Aktif' : 'Pakete dahil değil' }}
                    </span>
                </div>
                <h2 class="text-sm font-bold text-title mb-1">{{ $item['module']->name }}</h2>
                <p class="text-[12px] text-default mb-0">{{ $item['module']->description }}</p>
            </div>
        </div>
    @endforeach
</div>
@endsection
