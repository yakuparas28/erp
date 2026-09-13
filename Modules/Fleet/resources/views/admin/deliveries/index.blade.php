@extends('app.layouts.app')

@section('title', __('Key Handover'))

@section('content')
<h1 class="text-gray-900 text-xl font-bold mb-4">{{ __('Key Handover') }}</h1>

@if (session('success'))
    <div class="bg-success-transparent text-success border border-success rounded-md px-4 py-3 text-sm mb-4">{{ session('success') }}</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    @forelse ($pending as $r)
        <div class="bg-white border border-border-color rounded-md p-4">
            <div class="flex items-center justify-between mb-2">
                <div class="font-semibold">#{{ str_pad((string) $r->id, 5, '0', STR_PAD_LEFT) }} — {{ $r->vehicle?->plaka }}</div>
                <span class="{{ $r->teslim_beyani ? 'bg-success-transparent text-success' : 'bg-danger-transparent text-danger' }} px-2 py-1 rounded text-xs">
                    {{ $r->teslim_beyani ? __('No issues') : __('Reporting an issue') }}
                </span>
            </div>
            <dl class="text-sm space-y-1 mb-3">
                <div class="flex justify-between"><dt class="text-default">{{ __('Requester') }}</dt><dd>{{ $r->aktifSofor?->name }}</dd></div>
                <div class="flex justify-between"><dt class="text-default">{{ __('Project') }}</dt><dd>{{ $r->project?->ad ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-default">KM</dt><dd>{{ number_format($r->alis_km) }} → {{ number_format($r->teslim_km) }} <span class="text-xs text-default">(+{{ number_format($r->teslim_km - $r->alis_km) }})</span></dd></div>
                <div class="flex justify-between"><dt class="text-default">{{ __('Submitted at') }}</dt><dd>{{ $r->teslim_basvurusu_tarihi?->format('d.m.Y H:i') }}</dd></div>
            </dl>
            @if (! $r->teslim_beyani && $r->teslim_ariza_aciklamasi)
                <div class="bg-danger-transparent text-danger border border-danger rounded-md p-2 text-xs mb-3">{{ $r->teslim_ariza_aciklamasi }}</div>
            @endif
            @if ($r->teslim_fotograflari)
                <div class="grid grid-cols-5 gap-1 mb-3">
                    @foreach ($r->teslim_fotograflari as $slot => $path)
                        <a href="{{ \Storage::url($path) }}" target="_blank" class="block border border-border-color rounded overflow-hidden">
                            <img src="{{ \Storage::url($path) }}" alt="{{ $slot }}" class="w-full h-16 object-cover">
                        </a>
                    @endforeach
                </div>
            @endif
            <div class="flex gap-2">
                <form method="POST" action="{{ route('app.fleet.deliveries.confirm', $r) }}" class="flex-1" onsubmit="return confirm('Onaylıyor musunuz?');">
                    @csrf
                    <button class="w-full btn-sm bg-primary text-white">{{ __('Confirm Delivery') }}</button>
                </form>
                <form method="POST" action="{{ route('app.fleet.deliveries.force-garage', $r) }}" onsubmit="return confirm('İstisnai olarak garaja çekilecek. Onaylıyor musunuz?');">
                    @csrf
                    <button class="btn-sm bg-warning text-white">{{ __('Force to Garage') }}</button>
                </form>
            </div>
        </div>
    @empty
        <div class="col-span-2 p-6 text-center text-default bg-white border border-border-color rounded-md">{{ __('No records.') }}</div>
    @endforelse
</div>
<div class="mt-4">{{ $pending->links() }}</div>
@endsection
