@extends('app.layouts.app')

@section('title', __('Payment'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <div>
        <h1 class="text-gray-900 text-xl font-bold mb-1">{{ __('Payment') }} — {{ $payment->partner->name }}</h1>
        <p class="text-sm text-default mb-0">
            {{ __('Amount') }}: {{ $payment->amount }} &middot;
            {{ __('Unallocated') }}: {{ $payment->computed_unallocated_amount }}
        </p>
    </div>
    <a href="{{ route('app.accounting.payments.index') }}" class="btn-sm bg-white border border-border-color text-gray-900 inline-flex items-center gap-2 hover:bg-light">
        <i class="ph ph-arrow-left"></i> {{ __('Back to List') }}
    </a>
</div>

@error('amount')
    <div class="bg-danger-transparent text-danger border border-danger rounded-md px-4 py-3 text-sm mb-4">{{ $message }}</div>
@enderror

<div class="bg-white border border-border-color rounded-md mb-4">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-sm text-default border-b border-border-color">
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Invoice') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Allocated Amount') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Fx Difference') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($payment->allocations as $allocation)
                    <tr class="border-b border-border-color">
                        <td class="py-2.5 px-3 text-sm font-semibold text-title">{{ __('Invoice No.') }} {{ $allocation->invoice->id }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $allocation->allocated_amount }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $fxRevaluationsByInvoice->get($allocation->invoice_id) ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="py-8 text-center text-sm text-default">{{ __('No allocations yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if ($openInvoices->isNotEmpty())
    <div class="bg-white border border-border-color rounded-md p-4">
        <h2 class="text-base font-bold text-title mb-3">{{ __('Allocate') }}</h2>
        <form method="POST" action="{{ route('app.accounting.payments.allocations.store', $payment) }}" class="flex flex-wrap items-end gap-3">
            @csrf
            <div class="flex-1 min-w-40">
                <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Invoice') }}</label>
                <select name="invoice_id" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                    @foreach ($openInvoices as $openInvoice)
                        <option value="{{ $openInvoice->id }}">#{{ $openInvoice->id }} ({{ __('Remaining') }}: {{ $openInvoice->computed_remaining_balance }})</option>
                    @endforeach
                </select>
            </div>
            <div class="w-40">
                <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Amount') }}</label>
                <input type="number" step="0.0001" min="0.0001" name="amount" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
            </div>
            <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Allocate') }}</button>
        </form>
    </div>
@endif
@endsection
