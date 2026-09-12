@extends('app.layouts.app')

@section('title', __('Traceability') . ' — ' . $lot->lot_number)

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <div>
        <h1 class="text-gray-900 text-xl font-bold mb-1">{{ __('Traceability') }} — <span class="font-mono">{{ $lot->lot_number }}</span></h1>
        <p class="text-sm text-default mb-0">{{ $lot->product->name }} @if ($lot->expiry_date) · {{ __('Expiry') }}: {{ $lot->expiry_date->format('d.m.Y') }} @endif</p>
    </div>
    <a href="{{ route('app.inventory.lots.index') }}" class="btn-sm bg-white border border-border-color text-gray-900 inline-flex items-center gap-2 hover:bg-light">
        <i class="ph ph-arrow-left"></i> {{ __('Back to List') }}
    </a>
</div>

<div class="grid grid-cols-12 gap-4">
    <div class="col-span-12 lg:col-span-6">
        <div class="bg-white border border-border-color rounded-md">
            <div class="p-4 border-b border-border-color flex items-center gap-2">
                <i class="ph-duotone ph-arrow-down text-success"></i>
                <h2 class="text-base font-bold text-title mb-0">{{ __('Upstream') }} <span class="text-xs font-normal text-default">({{ __('where it came from') }})</span></h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-sm text-default border-b border-border-color">
                            <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Date') }}</th>
                            <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Qty') }}</th>
                            <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('To') }}</th>
                            <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Reference') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($inbound as $move)
                            <tr class="border-b border-border-color">
                                <td class="py-2.5 px-3 text-sm text-default">{{ $move->created_at->format('d.m.Y H:i') }}</td>
                                <td class="py-2.5 px-3 text-sm font-semibold text-success">+{{ $move->qty }}</td>
                                <td class="py-2.5 px-3 text-sm text-default">{{ $move->toLocation?->name ?? '—' }}</td>
                                <td class="py-2.5 px-3 text-xs text-default font-mono">{{ $move->reference_type }} #{{ $move->reference_id }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-6 text-center text-sm text-default">{{ __('No inbound moves.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-span-12 lg:col-span-6">
        <div class="bg-white border border-border-color rounded-md">
            <div class="p-4 border-b border-border-color flex items-center gap-2">
                <i class="ph-duotone ph-arrow-up text-warning"></i>
                <h2 class="text-base font-bold text-title mb-0">{{ __('Downstream') }} <span class="text-xs font-normal text-default">({{ __('where it went') }})</span></h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-sm text-default border-b border-border-color">
                            <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Date') }}</th>
                            <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Qty') }}</th>
                            <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('From') }}</th>
                            <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Reference') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($outbound as $move)
                            <tr class="border-b border-border-color">
                                <td class="py-2.5 px-3 text-sm text-default">{{ $move->created_at->format('d.m.Y H:i') }}</td>
                                <td class="py-2.5 px-3 text-sm font-semibold text-warning">{{ $move->qty }}</td>
                                <td class="py-2.5 px-3 text-sm text-default">{{ $move->fromLocation?->name ?? '—' }}</td>
                                <td class="py-2.5 px-3 text-xs text-default font-mono">{{ $move->reference_type }} #{{ $move->reference_id }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-6 text-center text-sm text-default">{{ __('No outbound moves.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
