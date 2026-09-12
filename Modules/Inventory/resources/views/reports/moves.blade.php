@extends('app.layouts.app')

@section('title', __('Moves History'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <div>
        <h1 class="text-gray-900 text-xl font-bold mb-1">{{ __('Moves History') }}</h1>
        <p class="text-sm text-default mb-0">{{ __('Immutable log of stock moves. Latest 500 entries.') }}</p>
    </div>
</div>

<form method="GET" action="{{ route('app.inventory.reports.moves') }}" class="bg-white border border-border-color rounded-md p-4 mb-4 grid grid-cols-12 gap-3">
    <div class="col-span-12 sm:col-span-3">
        <label class="text-xs text-default mb-1 block">{{ __('Product') }}</label>
        <select name="product_id" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
            <option value="">{{ __('— All') }}</option>
            @foreach ($products as $p)
                <option value="{{ $p->id }}" @selected(($filters['product_id'] ?? null) == $p->id)>{{ $p->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-span-12 sm:col-span-3">
        <label class="text-xs text-default mb-1 block">{{ __('Location') }}</label>
        <select name="location_id" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
            <option value="">{{ __('— All') }}</option>
            @foreach ($locations as $l)
                <option value="{{ $l->id }}" @selected(($filters['location_id'] ?? null) == $l->id)>{{ $l->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-span-6 sm:col-span-2">
        <label class="text-xs text-default mb-1 block">{{ __('Reference') }}</label>
        <select name="reference_type" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
            <option value="">{{ __('— All') }}</option>
            @foreach ($referenceTypes as $rt)
                <option value="{{ $rt }}" @selected(($filters['reference_type'] ?? null) === $rt)>{{ $rt }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-span-6 sm:col-span-2">
        <label class="text-xs text-default mb-1 block">{{ __('From') }}</label>
        <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
    </div>
    <div class="col-span-6 sm:col-span-2">
        <label class="text-xs text-default mb-1 block">{{ __('To') }}</label>
        <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
    </div>
    <div class="col-span-12 flex gap-2">
        <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Filter') }}</button>
        <a href="{{ route('app.inventory.reports.moves') }}" class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light">{{ __('Reset') }}</a>
    </div>
</form>

<div class="bg-white border border-border-color rounded-md">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-sm text-default border-b border-border-color">
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Date') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Product') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Qty') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('From') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('To') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Reference') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($moves as $move)
                    <tr class="border-b border-border-color">
                        <td class="py-2 px-3 text-sm text-default">{{ $move->created_at->format('d.m.Y H:i') }}</td>
                        <td class="py-2 px-3 text-sm font-semibold text-title">{{ $move->product->name }}</td>
                        <td class="py-2 px-3 text-sm {{ (float) $move->qty >= 0 ? 'text-success' : 'text-warning' }} font-semibold">{{ $move->qty }}</td>
                        <td class="py-2 px-3 text-sm text-default">{{ $move->fromLocation?->name ?? '—' }}</td>
                        <td class="py-2 px-3 text-sm text-default">{{ $move->toLocation?->name ?? '—' }}</td>
                        <td class="py-2 px-3 text-xs text-default font-mono">{{ $move->reference_type }} #{{ $move->reference_id }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-sm text-default">{{ __('No moves.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
