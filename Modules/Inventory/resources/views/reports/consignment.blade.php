@extends('app.layouts.app')

@section('title', __('Consignment Report'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <div>
        <h1 class="text-gray-900 text-xl font-bold mb-1">{{ __('Consignment Report') }}</h1>
        <p class="text-sm text-default mb-0">{{ __('Physical stock we hold on behalf of external owners (partners), grouped by owner.') }}</p>
    </div>
</div>

<div class="space-y-3">
    @forelse ($grouped as $ownerId => $rows)
        @php $owner = $rows->first()->ownerPartner; @endphp
        <div class="bg-white border border-border-color rounded-md">
            <div class="p-3 border-b border-border-color bg-light flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-bold text-title mb-0">{{ $owner->name }}</h2>
                    <p class="text-xs text-default mb-0">{{ __('Owner (consignor)') }}</p>
                </div>
                <span class="text-xs text-default">{{ __('Total items') }}: <strong>{{ $rows->count() }}</strong></span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-sm text-default border-b border-border-color">
                            <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Product') }}</th>
                            <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Location') }}</th>
                            <th class="text-right py-2 px-3 font-semibold text-gray-900">{{ __('Quantity') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $q)
                            <tr class="border-b border-border-color">
                                <td class="py-2 px-3 text-sm font-semibold text-title">{{ $q->product->name }}</td>
                                <td class="py-2 px-3 text-sm text-default">{{ $q->location->name }}</td>
                                <td class="py-2 px-3 text-sm text-default text-right">{{ $q->qty }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <div class="bg-white border border-border-color rounded-md p-8 text-center text-sm text-default">{{ __('No consignment stock on hand.') }}</div>
    @endforelse
</div>
@endsection
