@extends('app.layouts.app')

@section('title', __('My Reservations'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <h1 class="text-gray-900 text-xl font-bold mb-0">{{ __('My Reservations') }}</h1>
    @can('reserve vehicle')
        <a href="{{ route('app.fleet.reservations.create') }}" class="btn-sm bg-dark text-white border border-dark inline-flex items-center gap-2 hover:bg-primary-hover">
            <i class="ph ph-plus"></i> {{ __('New Reservation') }}
        </a>
    @endcan
</div>

@if (session('success'))
    <div class="bg-success-transparent text-success border border-success rounded-md px-4 py-3 text-sm mb-4">{{ session('success') }}</div>
@endif
@if ($errors->any())
    <div class="bg-danger-transparent text-danger border border-danger rounded-md px-4 py-3 text-sm mb-4">
        @foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
@endif

<div class="bg-white border border-border-color rounded-md overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-border-color text-left">
            <tr>
                <th class="p-3">#</th>
                <th class="p-3">{{ __('Vehicle') }}</th>
                <th class="p-3">{{ __('Project') }}</th>
                <th class="p-3">{{ __('Pickup Datetime') }}</th>
                <th class="p-3">{{ __('Return Datetime') }}</th>
                <th class="p-3">{{ __('Status') }}</th>
                <th class="p-3 text-right">{{ __('Actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($reservations as $r)
                @php
                    $status = $r->onay_durumu;
                    $badge = match ($status) {
                        'Onaylandi' => 'bg-success-transparent text-success',
                        'Reddedildi' => 'bg-danger-transparent text-danger',
                        default => 'bg-warning-transparent text-warning',
                    };
                @endphp
                <tr class="border-b border-border-color">
                    <td class="p-3 font-mono">#{{ str_pad((string) $r->id, 5, '0', STR_PAD_LEFT) }}</td>
                    <td class="p-3">
                        <div class="font-semibold">{{ $r->vehicle?->plaka ?? '—' }}</div>
                        <div class="text-xs text-default">{{ $r->vehicle?->marka_model }}</div>
                    </td>
                    <td class="p-3">{{ $r->project?->ad ?? '—' }}</td>
                    <td class="p-3">{{ $r->planlanan_alis_at?->format('d.m.Y H:i') }}</td>
                    <td class="p-3">{{ $r->planlanan_teslim_at?->format('d.m.Y H:i') }}</td>
                    <td class="p-3">
                        <span class="{{ $badge }} px-2 py-1 rounded text-xs">{{ __('reservation-status.'.$status) }}</span>
                        @if ($status === 'Reddedildi' && $r->red_aciklamasi)
                            <div class="text-xs text-danger mt-1">{{ $r->red_aciklamasi }}</div>
                        @endif
                    </td>
                    <td class="p-3 text-right">
                        @if ($status === 'Onaylandi' && $r->alis_tarihi === null)
                            <a href="{{ route('app.fleet.reservations.pickup', $r) }}" class="btn-sm bg-primary text-white">{{ __('Confirm Pickup') }}</a>
                        @elseif ($r->alis_tarihi !== null && $r->teslim_basvurusu_tarihi === null)
                            <a href="{{ route('app.fleet.reservations.delivery', $r) }}" class="btn-sm bg-warning text-white">{{ __('Submit Delivery') }}</a>
                        @elseif ($r->teslim_tarihi !== null)
                            <span class="text-xs text-success">{{ __('Completed') }}</span>
                        @else
                            <span class="text-xs text-default">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="p-6 text-center text-default">{{ __('No records.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $reservations->links() }}</div>
@endsection
