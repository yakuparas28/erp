<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <title>{{ __('Delivery Slip') }} — {{ $so->partner->name }}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif; padding: 24px; color: #111; font-size: 12px; }
        h1 { font-size: 20px; margin: 0 0 8px; }
        h2 { font-size: 14px; margin: 16px 0 8px; }
        .header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 24px; padding-bottom: 12px; border-bottom: 2px solid #111; }
        .partner-box { max-width: 45%; }
        .partner-box strong { display: block; margin-bottom: 4px; }
        .meta { text-align: right; font-size: 11px; color: #555; }
        .meta div { margin-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { padding: 8px 6px; text-align: left; border-bottom: 1px solid #ccc; }
        th { background: #f4f4f5; font-weight: 600; font-size: 11px; text-transform: uppercase; }
        .signature-block { margin-top: 60px; display: flex; justify-content: space-between; gap: 40px; }
        .signature-block > div { flex: 1; border-top: 1px solid #111; padding-top: 4px; font-size: 11px; text-align: center; }
        .print-only { display: block; }
        .no-print { margin: 12px 0; }
        @media print {
            .no-print { display: none; }
            body { padding: 0; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button type="button" onclick="window.print()" style="padding: 8px 16px; background: #111; color: white; border: none; border-radius: 4px; cursor: pointer;">{{ __('Print') }}</button>
        <a href="{{ route('app.sales.orders.show', $so) }}" style="margin-left: 12px;">← {{ __('Back to Order') }}</a>
    </div>

    <div class="header">
        <div class="partner-box">
            <strong>{{ __('Delivery Slip') }}</strong>
            <div>#SO-{{ $so->id }}</div>
        </div>
        <div class="meta">
            <div>{{ __('Date') }}: {{ $so->updated_at->format('d.m.Y') }}</div>
            <div>{{ __('Order') }}: SO-{{ $so->id }}</div>
            <div>{{ __('Source Location') }}: {{ $so->location->name ?? '—' }}</div>
        </div>
    </div>

    <h2>{{ __('Customer') }}</h2>
    <div>
        <strong>{{ $so->partner->name }}</strong>
        @if ($so->partner->tax_number)
            <div>{{ __('Tax No') }}: {{ $so->partner->tax_number }}</div>
        @endif
        @if ($so->partner->address)
            <div>{{ $so->partner->address }}</div>
        @endif
    </div>

    <h2>{{ __('Items') }}</h2>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>{{ __('Product') }}</th>
                <th style="text-align: right;">{{ __('Ordered') }}</th>
                <th style="text-align: right;">{{ __('Delivered') }}</th>
                <th>{{ __('Unit') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($so->lines as $i => $line)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $line->product->name }}</td>
                    <td style="text-align: right;">{{ $line->qty }}</td>
                    <td style="text-align: right;">{{ $line->delivered_qty }}</td>
                    <td>{{ $line->uom->name }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="signature-block">
        <div>{{ __('Prepared by') }}</div>
        <div>{{ __('Received by') }}</div>
    </div>
</body>
</html>
