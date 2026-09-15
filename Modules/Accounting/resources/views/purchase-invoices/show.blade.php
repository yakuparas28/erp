@extends('app.layouts.app')

@section('title', __('Purchase Invoice'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <div>
        <h1 class="text-gray-900 text-xl font-bold mb-1">{{ __('Purchase Invoice') }} — {{ $invoice->partner->name }}</h1>
        @include('accounting::purchase-invoices._status-badge', ['status' => $invoice->status])
        <span class="text-[11px] {{ $invoice->e_invoice_status === 'accepted' ? 'bg-success-transparent text-success' : ($invoice->e_invoice_status === 'rejected' ? 'bg-danger-transparent text-danger' : 'bg-light text-default') }} px-2 py-0.5 rounded ms-2">
            {{ __('e-invoice-status.'.$invoice->e_invoice_status) }}
        </span>
        @if ($invoice->currency_id)
            <span class="text-sm text-default ms-2">{{ __('Currency') }}: {{ $invoice->currency->code }} ({{ __('Rate') }}: {{ $invoice->exchange_rate_used }})</span>
        @endif
        @if ($invoice->source)
            <a href="{{ route('app.purchase.orders.show', $invoice->source_id) }}" class="text-sm text-default hover:underline ms-2">
                {{ __('Purchase Order') }} #{{ $invoice->source->id }}
            </a>
        @endif
    </div>
    <a href="{{ route('app.accounting.purchase-invoices.index') }}" class="btn-sm bg-white border border-border-color text-gray-900 inline-flex items-center gap-2 hover:bg-light">
        <i class="ph ph-arrow-left"></i> {{ __('Back to List') }}
    </a>
</div>

@error('invoice')
    <div class="bg-danger-transparent text-danger border border-danger rounded-md px-4 py-3 text-sm mb-4">{{ $message }}</div>
@enderror

@if ($invoice->source_type === 'purchase_order' && $invoice->matching_status !== 'not_applicable')
    @php
        $mBadge = match ($invoice->matching_status) {
            'matched' => ['bg-success-transparent text-success border-success', 'ph-check-circle', __('3-way match OK')],
            'mismatch' => ['bg-danger-transparent text-danger border-danger', 'ph-warning-circle', __('3-way match mismatch')],
            default => ['bg-warning-transparent text-warning border-warning', 'ph-clock', __('Awaiting goods receipt')],
        };
    @endphp
    <div class="bg-white border border-border-color rounded-md p-4 mb-4">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-base font-bold text-title inline-flex items-center gap-2">
                <i class="ph ph-scales"></i> {{ __('Three-way Matching') }}
            </h2>
            <span class="text-[11px] {{ $mBadge[0] }} border px-2 py-0.5 rounded inline-flex items-center gap-1">
                <i class="ph {{ $mBadge[1] }}"></i> {{ $mBadge[2] }}
            </span>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div class="bg-light rounded-md p-3">
                <div class="text-[11px] uppercase text-default mb-1">{{ __('Purchase Order') }}</div>
                <div class="text-title font-semibold">{{ number_format((float) $matchingBreakdown['po_amount'], 2) }}</div>
            </div>
            <div class="bg-light rounded-md p-3">
                <div class="text-[11px] uppercase text-default mb-1">{{ __('Received (× PO unit price)') }}</div>
                <div class="text-title font-semibold">{{ number_format((float) $matchingBreakdown['received_amount'], 2) }}</div>
            </div>
            <div class="bg-light rounded-md p-3">
                <div class="text-[11px] uppercase text-default mb-1">{{ __('Invoice Subtotal') }}</div>
                <div class="text-title font-semibold">{{ number_format((float) $matchingBreakdown['invoice_amount'], 2) }}</div>
            </div>
        </div>
    </div>
@endif

@if ($invoice->status === 'draft')
    <div class="bg-white border border-border-color rounded-md p-4 mb-4">
        <h2 class="text-base font-bold text-title mb-3">{{ __('Add Line') }}</h2>
        <form method="POST" action="{{ route('app.accounting.purchase-invoices.lines.store', $invoice) }}" class="flex flex-wrap items-end gap-3">
            @csrf
            <div class="flex-1 min-w-40">
                <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Product') }}</label>
                <select name="product_id" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                    @foreach ($invoice->source->lines as $poLine)
                        <option value="{{ $poLine->product_id }}">{{ $poLine->product->name }} ({{ __('ordered') }}: {{ $poLine->qty }}, {{ __('received') }}: {{ $poLine->receivedQty() }})</option>
                    @endforeach
                </select>
            </div>
            <div class="w-28">
                <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Quantity') }}</label>
                <input type="number" step="0.0001" min="0.0001" name="qty" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
            </div>
            <div class="w-28">
                <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Unit Price') }}</label>
                <input type="number" step="0.0001" min="0" name="unit_price" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
            </div>
            <div class="w-40">
                <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Tax Rate') }}</label>
                <select name="tax_rate_id" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                    <option value="">{{ __('None') }}</option>
                    @foreach ($taxRates as $taxRate)
                        <option value="{{ $taxRate->id }}">{{ $taxRate->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Add') }}</button>
        </form>
        @error('qty')
            <p class="text-[11px] text-danger mt-2 mb-0">{{ $message }}</p>
        @enderror
    </div>
@endif

<div class="bg-white border border-border-color rounded-md">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-sm text-default border-b border-border-color">
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Product') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Quantity') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Unit Price') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Tax Rate') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Subtotal') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Tax Amount') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($invoice->lines as $line)
                    <tr class="border-b border-border-color">
                        <td class="py-2.5 px-3 text-sm font-semibold text-title">{{ $line->product->name }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $line->qty }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $line->unit_price }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $line->taxRate?->name ?? __('None') }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $line->subtotal() }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $line->taxAmount() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-sm text-default">{{ __('No lines yet.') }}</td></tr>
                @endforelse
            </tbody>
            @if ($invoice->lines->isNotEmpty())
                <tfoot>
                    <tr>
                        <td colspan="4" class="py-2.5 px-3 text-sm font-semibold text-title text-right">{{ __('Total') }}</td>
                        <td colspan="2" class="py-2.5 px-3 text-sm font-semibold text-title">
                            {{ $invoice->total() }}{{ $invoice->currency_id ? ' '.$invoice->currency->code : '' }}
                            @if ($invoice->currency_id)
                                <span class="block text-xs font-normal text-default">{{ __('TL Equivalent') }}: {{ $invoice->computed_total_tl }}</span>
                            @endif
                        </td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>

@php
    $requiresApproval = app(\Modules\Accounting\Services\InvoiceService::class)->requiresApproval($invoice);
    $approval = $invoice->approval;
    $canApprove = auth()->user()->hasRole('Tenant Admin');
    $canPost = $invoice->status === 'draft' && $invoice->lines->isNotEmpty()
        && (! $requiresApproval || $invoice->isApproved());
@endphp

@if ($invoice->status === 'draft' && $requiresApproval)
    <div class="bg-white border border-border-color rounded-md p-4 mt-4">
        <h2 class="text-base font-bold text-title mb-2 inline-flex items-center gap-2">
            <i class="ph ph-shield-check"></i> {{ __('Approval Required') }}
        </h2>
        <p class="text-[12px] text-default mb-3">
            {{ __('This invoice exceeds the approval threshold and must be approved before it can be posted.') }}
        </p>

        @if ($approval === null)
            <form method="POST" action="{{ route('app.accounting.invoices.approval.submit', $invoice) }}">
                @csrf
                <button type="submit" class="btn-sm bg-warning text-white border border-warning hover:bg-warning/90 cursor-pointer inline-flex items-center gap-2">
                    <i class="ph ph-paper-plane-tilt"></i> {{ __('Submit for Approval') }}
                </button>
            </form>
        @elseif ($approval->status === 'pending')
            <div class="flex items-center gap-2 mb-3">
                <span class="text-[11px] bg-warning-transparent text-warning border border-warning px-2 py-0.5 rounded inline-flex items-center gap-1">
                    <i class="ph ph-clock"></i> {{ __('Awaiting approval') }}
                </span>
                <span class="text-[12px] text-default">{{ __('Submitted') }} {{ $approval->submitted_at?->diffForHumans() }}</span>
            </div>
            @if ($canApprove)
                <div class="flex items-center gap-2">
                    <form method="POST" action="{{ route('app.accounting.invoices.approval.approve', $invoice) }}">
                        @csrf
                        <button type="submit" class="btn-sm bg-success text-white border border-success hover:bg-success/90 cursor-pointer inline-flex items-center gap-1">
                            <i class="ph ph-check"></i> {{ __('Approve') }}
                        </button>
                    </form>
                    <form method="POST" action="{{ route('app.accounting.invoices.approval.reject', $invoice) }}">
                        @csrf
                        <button type="submit" class="btn-sm bg-white border border-danger text-danger hover:bg-danger hover:text-white cursor-pointer inline-flex items-center gap-1">
                            <i class="ph ph-x"></i> {{ __('Reject') }}
                        </button>
                    </form>
                </div>
            @endif
        @elseif ($approval->status === 'approved')
            <span class="text-[11px] bg-success-transparent text-success border border-success px-2 py-0.5 rounded inline-flex items-center gap-1">
                <i class="ph ph-check-circle"></i> {{ __('Approved') }} · {{ $approval->decided_at?->diffForHumans() }}
            </span>
        @elseif ($approval->status === 'rejected')
            <span class="text-[11px] bg-danger-transparent text-danger border border-danger px-2 py-0.5 rounded inline-flex items-center gap-1">
                <i class="ph ph-x-circle"></i> {{ __('Rejected') }} · {{ $approval->decided_at?->diffForHumans() }}
            </span>
        @endif
    </div>
@endif

@if ($canPost)
    <div class="flex items-center gap-2 mt-4">
        <form method="POST" action="{{ route('app.accounting.purchase-invoices.post', $invoice) }}">
            @csrf
            <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Post') }}</button>
        </form>
    </div>
@endif

@if ($invoice->status === 'posted' && $invoice->e_invoice_status === 'not_sent')
    <div class="flex items-center gap-2 mt-4">
        <form method="POST" action="{{ route('app.accounting.purchase-invoices.e-invoice.send', $invoice) }}">
            @csrf
            <button type="submit" class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light cursor-pointer">{{ __('Send e-Invoice') }}</button>
        </form>
    </div>
@elseif ($invoice->e_invoice_status === 'sent')
    <div class="flex items-center gap-2 mt-4">
        <form method="POST" action="{{ route('app.accounting.purchase-invoices.e-invoice.accept', $invoice) }}">
            @csrf
            <button type="submit" class="btn-sm bg-white border border-success text-success hover:bg-success hover:text-white cursor-pointer">{{ __('Mark Accepted') }}</button>
        </form>
        <form method="POST" action="{{ route('app.accounting.purchase-invoices.e-invoice.reject', $invoice) }}">
            @csrf
            <button type="submit" class="btn-sm bg-white border border-danger text-danger hover:bg-danger hover:text-white cursor-pointer">{{ __('Mark Rejected') }}</button>
        </form>
    </div>
@endif
@endsection
