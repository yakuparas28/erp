<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <title>{{ __('Quotation') }} — {{ $so->partner->name }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('template/v1/assets/libs/@phosphor-icons/web/duotone/style.css') }}">
    <link rel="stylesheet" href="{{ asset('template/v1/assets/libs/@phosphor-icons/web/regular/style.css') }}">
    <link rel="stylesheet" href="{{ asset('template/v1/assets/css/style.css') }}">
</head>
<body class="bg-light">
    <div class="max-w-3xl mx-auto py-8 px-4">
        @if (session('status'))
            <div class="bg-success-transparent text-success border border-success rounded-md px-4 py-3 text-sm mb-4">{{ session('status') }}</div>
        @endif
        @error('portal')
            <div class="bg-danger-transparent text-danger border border-danger rounded-md px-4 py-3 text-sm mb-4">{{ $message }}</div>
        @enderror

        <div class="bg-white border border-border-color rounded-md p-8 shadow-sm">
            <div class="flex items-start justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-title mb-1">{{ __('Quotation') }}</h1>
                    <p class="text-sm text-default mb-0">SO-{{ str_pad((string) $so->id, 5, '0', STR_PAD_LEFT) }}</p>
                </div>
                <div class="text-right">
                    <p class="text-xs text-default mb-0">{{ __('Date') }}</p>
                    <p class="text-sm font-semibold text-title mb-2">{{ $so->sent_at?->format('d.m.Y') ?? $so->created_at->format('d.m.Y') }}</p>
                    @if ($so->validity_date)
                        <p class="text-xs text-default mb-0">{{ __('Valid Until') }}</p>
                        <p class="text-sm font-semibold {{ $so->validity_date->isPast() ? 'text-danger' : 'text-title' }}">{{ $so->validity_date->format('d.m.Y') }}</p>
                    @endif
                </div>
            </div>

            <div class="mb-6 pb-6 border-b border-border-color">
                <h3 class="text-xs font-semibold text-default uppercase mb-2">{{ __('To') }}</h3>
                <p class="text-sm font-semibold text-title mb-0">{{ $so->partner->name }}</p>
                @if (! empty($so->partner->tax_number))
                    <p class="text-xs text-default mb-0">{{ __('Tax No') }}: {{ $so->partner->tax_number }}</p>
                @endif
            </div>

            <table class="w-full text-sm mb-6">
                <thead>
                    <tr class="border-b border-border-color">
                        <th class="text-left py-2 font-semibold text-title">{{ __('Item') }}</th>
                        <th class="text-right py-2 font-semibold text-title">{{ __('Qty') }}</th>
                        <th class="text-right py-2 font-semibold text-title">{{ __('Unit Price') }}</th>
                        <th class="text-right py-2 font-semibold text-title">{{ __('Total') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($so->lines as $line)
                        @php $lineTotal = bcmul((string) $line->qty, (string) $line->unit_price, 4); @endphp
                        <tr class="border-b border-border-color">
                            <td class="py-2">{{ $line->product->name }}</td>
                            <td class="py-2 text-right">{{ $line->qty }} {{ $line->uom->name }}</td>
                            <td class="py-2 text-right">{{ $line->unit_price }}</td>
                            <td class="py-2 text-right font-semibold">{{ $lineTotal }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    @php $grandTotal = $so->lines->reduce(fn ($c, $l) => bcadd($c, bcmul((string) $l->qty, (string) $l->unit_price, 4), 4), '0'); @endphp
                    <tr>
                        <td colspan="3" class="py-3 text-right font-semibold text-title">{{ __('Total (Net)') }}</td>
                        <td class="py-3 text-right font-bold text-lg text-title">{{ $grandTotal }}</td>
                    </tr>
                </tfoot>
            </table>

            @if ($so->status === 'quotation_sent')
                <div class="pt-6 border-t border-border-color">
                    <p class="text-sm text-default mb-4">{{ __('Please review the quotation. You can accept to convert it into a firm order, or decline it.') }}</p>
                    <div class="flex flex-wrap items-center gap-3">
                        <form method="POST" action="{{ route('portal.quote.accept', ['token' => $so->access_token]) }}">
                            @csrf
                            <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer inline-flex items-center gap-2 py-3 px-6 text-base">
                                <i class="ph ph-check-circle"></i> {{ __('Accept Quotation') }}
                            </button>
                        </form>
                        <form method="POST" action="{{ route('portal.quote.decline', ['token' => $so->access_token]) }}" onsubmit="return confirm('{{ __('Decline this quotation?') }}')">
                            @csrf
                            <button type="submit" class="btn-sm bg-white border border-danger text-danger hover:bg-danger hover:text-white cursor-pointer inline-flex items-center gap-2 py-3 px-6 text-base">
                                <i class="ph ph-x-circle"></i> {{ __('Decline') }}
                            </button>
                        </form>
                    </div>
                </div>
            @elseif ($so->status === 'confirmed')
                <div class="pt-6 border-t border-border-color">
                    <div class="bg-success-transparent text-success border border-success rounded-md px-4 py-3 text-sm">
                        <i class="ph ph-check-circle"></i>
                        {{ __('This quotation was accepted on :date.', ['date' => $so->customer_confirmed_at?->format('d.m.Y H:i') ?? '—']) }}
                    </div>
                </div>
            @elseif ($so->status === 'cancelled')
                <div class="pt-6 border-t border-border-color">
                    <div class="bg-danger-transparent text-danger border border-danger rounded-md px-4 py-3 text-sm">
                        <i class="ph ph-x-circle"></i>
                        {{ __('This quotation was declined.') }}
                    </div>
                </div>
            @endif
        </div>
    </div>
</body>
</html>
