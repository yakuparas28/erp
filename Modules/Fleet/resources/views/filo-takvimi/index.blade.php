@extends('app.layouts.app')

@section('title', __('Fleet Calendar'))

@section('content')
@php
    $days = [];
    for ($d = $start->copy(); $d->lte($end); $d = $d->addDay()) { $days[] = $d; }
    $prev = $start->subMonth();
    $next = $start->addMonth();
@endphp

<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <h1 class="text-gray-900 text-xl font-bold mb-0">{{ __('Fleet Calendar') }}</h1>
    <div class="flex items-center gap-2">
        <a href="{{ route('app.fleet.calendar.index', ['year' => $prev->year, 'month' => $prev->month]) }}" class="btn-sm border border-border-color"><i class="ph ph-caret-left"></i></a>
        <div class="text-sm font-semibold">{{ $start->translatedFormat('F Y') }}</div>
        <a href="{{ route('app.fleet.calendar.index', ['year' => $next->year, 'month' => $next->month]) }}" class="btn-sm border border-border-color"><i class="ph ph-caret-right"></i></a>
        <a href="{{ route('app.fleet.calendar.index') }}" class="btn-sm border border-border-color">{{ __('Today') }}</a>
    </div>
</div>

<div class="bg-white border border-border-color rounded-md overflow-x-auto">
    <table class="w-full text-xs border-collapse">
        <thead class="bg-gray-50 border-b border-border-color">
            <tr>
                <th class="p-2 sticky left-0 bg-gray-50 text-left">{{ __('Plate') }}</th>
                @foreach ($days as $d)
                    <th class="p-1 text-center w-8 {{ $d->isWeekend() ? 'bg-gray-100' : '' }}">{{ $d->day }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($vehicles as $v)
                <tr class="border-b border-border-color">
                    <td class="p-2 sticky left-0 bg-white font-semibold whitespace-nowrap">{{ $v->plaka }}<div class="text-[10px] text-default font-normal">{{ $v->marka_model }}</div></td>
                    @foreach ($days as $d)
                        @php
                            $dStr = $d->toDateString();
                            $bloke = ($blocks[$v->id] ?? collect())->first(fn ($b) => $dStr >= $b->start_date->toDateString() && $dStr <= $b->end_date->toDateString());
                            $rez = ($reservations[$v->id] ?? collect())->first(fn ($r) => $dStr >= $r->planlanan_alis_at->toDateString() && $dStr <= $r->planlanan_teslim_at->toDateString());
                            $cls = 'bg-white';
                            $title = '';
                            if ($bloke) { $cls = 'bg-danger-transparent'; $title = __('block-type.'.$bloke->block_type).' — '.$bloke->aciklama; }
                            elseif ($rez && $rez->onay_durumu === 'Onaylandi') { $cls = 'bg-success-transparent'; $title = $rez->aktifSofor?->name.' · '.__('Approved'); }
                            elseif ($rez) { $cls = 'bg-warning-transparent'; $title = $rez->aktifSofor?->name.' · '.__('Pending'); }
                        @endphp
                        <td class="p-1 text-center border border-border-color {{ $cls }}" title="{{ $title }}"></td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div class="mt-4 flex flex-wrap gap-3 text-xs">
    <span class="inline-flex items-center gap-1"><span class="w-3 h-3 bg-success-transparent inline-block"></span> {{ __('Approved') }}</span>
    <span class="inline-flex items-center gap-1"><span class="w-3 h-3 bg-warning-transparent inline-block"></span> {{ __('Pending') }}</span>
    <span class="inline-flex items-center gap-1"><span class="w-3 h-3 bg-danger-transparent inline-block"></span> {{ __('Blocked') }}</span>
</div>
@endsection
