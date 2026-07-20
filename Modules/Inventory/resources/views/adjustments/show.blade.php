@extends('app.layouts.app')

@section('title', __('Stock Count'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <div>
        <h1 class="text-gray-900 text-xl font-bold mb-1">{{ __('Stock Count') }} — {{ $adjustment->location->name }}</h1>
        <span class="text-[11px] {{ $adjustment->status === 'approved' ? 'bg-success-transparent text-success' : ($adjustment->status === 'cancelled' ? 'bg-light text-default' : 'bg-warning-transparent text-warning') }} px-2 py-0.5 rounded">
            {{ __('adjustment-status.'.$adjustment->status) }}
        </span>
    </div>
    <a href="{{ route('app.inventory.adjustments.index') }}" class="btn-sm bg-white border border-border-color text-gray-900 inline-flex items-center gap-2 hover:bg-light">
        <i class="ph ph-arrow-left"></i> {{ __('Back to List') }}
    </a>
</div>

@if ($adjustment->status === 'counting')
    <div class="bg-white border border-border-color rounded-md p-4 mb-4 max-w-xl">
        <h2 class="text-base font-bold text-title mb-3">{{ __('Record a Count') }}</h2>
        <form method="POST" action="{{ route('app.inventory.adjustments.counts.store', $adjustment) }}" class="flex flex-wrap items-end gap-3">
            @csrf
            <div class="flex-1 min-w-40">
                <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Product') }}</label>
                <select name="product_id" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}">{{ $product->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-32">
                <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Quantity') }}</label>
                <input type="number" step="0.0001" min="0.0001" name="qty" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
            </div>
            <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Add') }}</button>
        </form>
        <p class="text-[11px] text-default mt-2 mb-0">{{ __('Counting the same product again adds to the previous count (useful when multiple workers count in parallel).') }}</p>
    </div>
@endif

<div class="bg-white border border-border-color rounded-md">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-sm text-default border-b border-border-color">
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Product') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Counted') }}</th>
                    @if ($showTheoretical)
                        <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('System Quantity') }}</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($adjustment->lines as $line)
                    <tr class="border-b border-border-color">
                        <td class="py-2.5 px-3 text-sm font-semibold text-title">{{ $line->product->name }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $line->counted_qty }}</td>
                        @if ($showTheoretical)
                            <td class="py-2.5 px-3 text-sm text-default">{{ $line->theoretical_qty ?? __('hidden while counting') }}</td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="3" class="py-8 text-center text-sm text-default">{{ __('No counts recorded yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="flex items-center gap-2 mt-4">
    @if ($adjustment->status === 'counting')
        <form method="POST" action="{{ route('app.inventory.adjustments.submit', $adjustment) }}">
            @csrf
            <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Submit for Approval') }}</button>
        </form>
        <form method="POST" action="{{ route('app.inventory.adjustments.cancel', $adjustment) }}">
            @csrf
            <button type="submit" class="btn-sm bg-white border border-danger text-danger hover:bg-danger hover:text-white cursor-pointer">{{ __('Cancel') }}</button>
        </form>
    @elseif ($adjustment->status === 'pending_approval')
        @if ($canApprove)
            <form method="POST" action="{{ route('app.inventory.adjustments.approve', $adjustment) }}">
                @csrf
                <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Approve') }}</button>
            </form>
        @else
            <p class="text-sm text-default">{{ __('Waiting for approval from another administrator (you cannot approve your own count).') }}</p>
        @endif
        <form method="POST" action="{{ route('app.inventory.adjustments.cancel', $adjustment) }}">
            @csrf
            <button type="submit" class="btn-sm bg-white border border-danger text-danger hover:bg-danger hover:text-white cursor-pointer">{{ __('Cancel') }}</button>
        </form>
    @endif
</div>
@endsection
