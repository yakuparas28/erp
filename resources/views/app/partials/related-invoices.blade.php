{{-- İlgili faturalar widget'ı — SO/PO show sayfalarına include edilir.
     $invoices koleksiyonu Invoice modellerini içerir. --}}
@php
    $badge = fn (string $s) => match ($s) {
        'draft' => 'bg-light text-default',
        'posted' => 'bg-info-transparent text-info',
        'paid' => 'bg-success-transparent text-success',
        'cancelled' => 'bg-danger-transparent text-danger',
        default => 'bg-light text-default',
    };
    $prefix = ($routePrefix ?? 'purchase') === 'sales' ? 'SI' : 'PI';
    $showRoute = ($routePrefix ?? 'purchase') === 'sales'
        ? 'app.accounting.sales-invoices.show'
        : 'app.accounting.purchase-invoices.show';
@endphp
<div class="bg-white border border-border-color rounded-md p-4 mb-4">
    <div class="flex items-center justify-between mb-3">
        <h2 class="text-base font-bold text-title inline-flex items-center gap-2">
            <i class="ph ph-receipt"></i> {{ __('Related Invoices') }}
            <span class="text-xs text-default">({{ $invoices->count() }})</span>
        </h2>
    </div>
    @if ($invoices->isEmpty())
        <p class="text-sm text-default mb-0">{{ __('No invoices linked to this document yet.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-xs text-default border-b border-border-color">
                    <th class="text-left py-1.5 px-2 font-semibold">{{ __('Reference') }}</th>
                    <th class="text-left py-1.5 px-2 font-semibold">{{ __('Date') }}</th>
                    <th class="text-right py-1.5 px-2 font-semibold">{{ __('Amount') }}</th>
                    <th class="text-left py-1.5 px-2 font-semibold">{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoices as $inv)
                    <tr class="border-b border-border-color last:border-0">
                        <td class="py-1.5 px-2">
                            <a href="{{ route($showRoute, $inv) }}" class="font-mono text-default hover:text-primary">
                                #{{ $prefix }}{{ str_pad((string) $inv->id, 5, '0', STR_PAD_LEFT) }}
                            </a>
                        </td>
                        <td class="py-1.5 px-2 text-default">{{ $inv->created_at->translatedFormat('d M Y') }}</td>
                        <td class="py-1.5 px-2 text-right font-semibold text-title">{{ number_format((float) $inv->total(), 2) }}</td>
                        <td class="py-1.5 px-2">
                            <span class="text-[11px] {{ $badge($inv->status) }} px-2 py-0.5 rounded">
                                {{ __('invoice-status.'.$inv->status) }}
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
