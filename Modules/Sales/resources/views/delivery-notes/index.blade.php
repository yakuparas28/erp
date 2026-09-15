@extends('app.layouts.app')

@section('title', __('Delivery Notes'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <div>
        <h1 class="text-gray-900 text-xl max-lg:text-lg font-bold mb-0">{{ __('Delivery Notes') }}</h1>
        <p class="text-[12px] text-default mb-0 mt-1">
            {{ __('Automatically generated for each shipped sales order line.') }}
        </p>
    </div>
</div>

<div class="bg-white border border-border-color rounded-md p-4">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-default text-[11px] uppercase font-medium">
                    <th class="text-left py-2 border-b border-border-color">{{ __('Note No') }}</th>
                    <th class="text-left py-2 border-b border-border-color">{{ __('Date') }}</th>
                    <th class="text-left py-2 border-b border-border-color">{{ __('Sales Order') }}</th>
                    <th class="text-left py-2 border-b border-border-color">{{ __('Customer') }}</th>
                    <th class="text-left py-2 border-b border-border-color">{{ __('Vehicle') }}</th>
                    <th class="text-right py-2 border-b border-border-color">{{ __('Lines') }}</th>
                    <th class="text-left py-2 border-b border-border-color">{{ __('Status') }}</th>
                    <th class="py-2 border-b border-border-color"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($notes as $note)
                <tr class="border-b border-border-color">
                    <td class="py-2 font-medium text-gray-900">{{ $note->note_no }}</td>
                    <td class="py-2 text-default">{{ optional($note->delivery_date)->format('d.m.Y') }}</td>
                    <td class="py-2">
                        <a href="{{ route('app.sales.orders.show', $note->sales_order_id) }}" class="text-primary hover:underline">SO-{{ $note->sales_order_id }}</a>
                    </td>
                    <td class="py-2 text-gray-900">{{ optional($note->salesOrder?->partner)->name ?? '—' }}</td>
                    <td class="py-2 text-default">{{ $note->vehicle_plate ?: '—' }}</td>
                    <td class="py-2 text-right text-default">{{ $note->lines->count() }}</td>
                    <td class="py-2">
                        <span class="px-2 py-0.5 rounded text-[11px] {{ $note->status === 'delivered' ? 'bg-success-transparent text-success' : ($note->status === 'cancelled' ? 'bg-danger-transparent text-danger' : 'bg-light text-default') }}">
                            {{ __(ucfirst($note->status)) }}
                        </span>
                    </td>
                    <td class="py-2 text-right">
                        <a href="{{ route('app.sales.delivery-notes.show', $note) }}" class="text-primary text-[12px] hover:underline">{{ __('View') }}</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="py-6 text-center text-default">{{ __('No delivery notes yet.') }}</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $notes->links() }}</div>
</div>
@endsection
