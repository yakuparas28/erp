@extends('app.layouts.app')

@section('title', __('Fleet Dashboard'))

@section('content')
<h1 class="text-xl font-bold mb-4">{{ __('Fleet Dashboard') }}</h1>

<div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-4">
    <div class="bg-white border border-border-color rounded-md p-4"><div class="text-xs text-default">Toplam Araç</div><div class="text-2xl font-bold">{{ $vehicles->count() }}</div></div>
    <div class="bg-white border border-border-color rounded-md p-4"><div class="text-xs text-default">Aktif Yolculuk</div><div class="text-2xl font-bold text-primary">{{ $kpis['active_rides'] }}</div></div>
    <div class="bg-white border border-border-color rounded-md p-4"><div class="text-xs text-default">Onay Bekleyen</div><div class="text-2xl font-bold text-warning">{{ $kpis['pending_approvals'] }}</div></div>
    <div class="bg-white border border-border-color rounded-md p-4"><div class="text-xs text-default">Kritik Pencere</div><div class="text-2xl font-bold text-warning">{{ $kpis['critical_count'] }}</div></div>
    <div class="bg-white border border-border-color rounded-md p-4"><div class="text-xs text-default">MTV Gecikmiş</div><div class="text-2xl font-bold text-danger">{{ $kpis['mtv_overdue'] }}</div></div>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
    @foreach (['Garajda' => 'success', 'Aktif_Kullanimda' => 'primary', 'Blokeli_Bakimda' => 'danger'] as $key => $color)
        <div class="bg-white border border-border-color rounded-md p-4">
            <div class="text-xs text-default mb-1">{{ __('vehicle-status.'.$key) }}</div>
            <div class="text-2xl font-bold text-{{ $color }}">{{ ($byDurum[$key] ?? collect())->count() }}</div>
            <ul class="mt-2 space-y-1 text-xs max-h-40 overflow-y-auto">
                @foreach ($byDurum[$key] ?? [] as $v)
                    <li class="flex justify-between"><span>{{ $v->plaka }}</span><span class="text-default">{{ $v->marka_model }}</span></li>
                @endforeach
            </ul>
        </div>
    @endforeach
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <div class="bg-white border border-border-color rounded-md p-4">
        <div class="font-semibold mb-3">Yaklaşan Bakım / Muayene / MTV (90 gün)</div>
        <table class="w-full text-sm">
            <tbody>
                @forelse ($upcomingMaintenance->take(15) as $item)
                    @php
                        $cls = $item['days'] <= 30 ? 'text-danger' : ($item['days'] <= 60 ? 'text-warning' : 'text-info');
                    @endphp
                    <tr class="border-b border-border-color">
                        <td class="py-2">{{ $item['vehicle']->plaka }} <span class="text-xs text-default">{{ $item['tur'] }}</span></td>
                        <td class="py-2 text-right {{ $cls }}">{{ $item['date']->format('d.m.Y') }} <span class="text-xs">({{ $item['days'] }} gün)</span></td>
                    </tr>
                @empty
                    <tr><td class="py-3 text-center text-default">{{ __('No records.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="bg-white border border-border-color rounded-md p-4">
        <div class="font-semibold mb-3">Aktif Yolculuklar</div>
        <table class="w-full text-sm">
            <tbody>
                @forelse ($activeRides as $r)
                    <tr class="border-b border-border-color">
                        <td class="py-2">{{ $r->vehicle?->plaka }} <span class="text-xs text-default">{{ $r->aktifSofor?->name }}</span></td>
                        <td class="py-2 text-right text-xs text-default">Alış: {{ $r->alis_tarihi?->format('d.m H:i') }}</td>
                    </tr>
                @empty
                    <tr><td class="py-3 text-center text-default">Aktif yolculuk yok.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if ($mtvOverdue->isNotEmpty())
    <div class="mt-4 bg-danger-transparent border border-danger rounded-md p-4">
        <div class="font-semibold text-danger mb-2">MTV Gecikmiş Araçlar</div>
        <ul class="text-sm space-y-1">
            @foreach ($mtvOverdue as $v)
                <li>{{ $v->plaka }} — {{ $v->marka_model }} · MTV: {{ $v->mtv_odeme_tarihi->format('d.m.Y') }}</li>
            @endforeach
        </ul>
    </div>
@endif
@endsection
