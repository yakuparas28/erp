@php
    $items = $items ?? collect();
    $isSales = ($mode ?? 'sales') === 'sales';
    $heading = $isSales ? __('Related Delivery Notes') : __('Related Goods Receipts');
    $emptyText = $isSales ? __('No delivery notes yet.') : __('No goods receipts yet.');
    $showRoute = $isSales ? 'app.sales.delivery-notes.show' : 'app.purchase.goods-receipts.show';
    $dateField = $isSales ? 'delivery_date' : 'receipt_date';
    $noField = $isSales ? 'note_no' : 'receipt_no';
@endphp

<div class="bg-white border border-border-color rounded-md p-4 mb-4">
    <div class="flex items-center justify-between mb-3">
        <h2 class="text-gray-900 font-semibold mb-0">{{ $heading }}</h2>
        <span class="text-[11px] text-default">{{ $items->count() }}</span>
    </div>
    @if ($items->isEmpty())
        <div class="text-center text-default py-4 text-sm">{{ $emptyText }}</div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-default text-[11px] uppercase font-medium">
                        <th class="text-left py-2 border-b border-border-color">{{ __('Note No') }}</th>
                        <th class="text-left py-2 border-b border-border-color">{{ __('Date') }}</th>
                        <th class="text-right py-2 border-b border-border-color">{{ __('Lines') }}</th>
                        <th class="text-left py-2 border-b border-border-color">{{ __('Status') }}</th>
                        <th class="py-2 border-b border-border-color"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $item)
                    <tr class="border-b border-border-color">
                        <td class="py-2 font-medium text-gray-900">{{ $item->{$noField} }}</td>
                        <td class="py-2 text-default">{{ optional($item->{$dateField})->format('d.m.Y') }}</td>
                        <td class="py-2 text-right text-default">{{ $item->lines_count ?? $item->lines->count() }}</td>
                        <td class="py-2">
                            <span class="px-2 py-0.5 rounded text-[11px] {{ in_array($item->status, ['delivered', 'received']) ? 'bg-success-transparent text-success' : ($item->status === 'cancelled' ? 'bg-danger-transparent text-danger' : 'bg-light text-default') }}">
                                {{ __(ucfirst($item->status)) }}
                            </span>
                        </td>
                        <td class="py-2 text-right">
                            <a href="{{ route($showRoute, $item) }}" class="text-primary text-[12px] hover:underline">{{ __('View') }}</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
