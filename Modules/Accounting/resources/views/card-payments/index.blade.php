@extends('app.layouts.app')

@section('title', __('Card Payments'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <div>
        <h1 class="text-gray-900 text-xl max-lg:text-lg font-bold mb-0">{{ __('Card Payments') }}</h1>
        <p class="text-[12px] text-default mb-0 mt-1">
            {{ __('POS / credit card collections. Commission and net are computed automatically; settlement moves the net into the bank account.') }}
        </p>
    </div>
    <button type="button" data-card-open-add class="btn-sm bg-primary text-white border border-primary hover:bg-primary/90 cursor-pointer inline-flex items-center gap-2">
        <i class="ph ph-plus"></i> {{ __('New Card Payment') }}
    </button>
</div>

@if (session('status'))
    <div class="bg-success-transparent text-success border border-success rounded-md px-4 py-3 text-sm mb-4">{{ session('status') }}</div>
@endif
@error('card')
    <div class="bg-danger-transparent text-danger border border-danger rounded-md px-4 py-3 text-sm mb-4">{{ $message }}</div>
@enderror

<div class="bg-white border border-border-color rounded-md p-4">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-default text-[11px] uppercase font-medium">
                    <th class="text-left py-2 border-b border-border-color">{{ __('Date') }}</th>
                    <th class="text-left py-2 border-b border-border-color">{{ __('Terminal') }}</th>
                    <th class="text-left py-2 border-b border-border-color">{{ __('Customer') }}</th>
                    <th class="text-right py-2 border-b border-border-color">{{ __('Gross') }}</th>
                    <th class="text-center py-2 border-b border-border-color">{{ __('Inst.') }}</th>
                    <th class="text-right py-2 border-b border-border-color">{{ __('Commission') }}</th>
                    <th class="text-right py-2 border-b border-border-color">{{ __('Net') }}</th>
                    <th class="text-left py-2 border-b border-border-color">{{ __('Settlement') }}</th>
                    <th class="text-left py-2 border-b border-border-color">{{ __('Status') }}</th>
                    <th class="py-2 border-b border-border-color"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($payments as $card)
                <tr class="border-b border-border-color">
                    <td class="py-2 text-default">{{ $card->transaction_date->format('d.m.Y') }}</td>
                    <td class="py-2 text-default">{{ optional($card->terminal)->name }}</td>
                    <td class="py-2 text-gray-900">{{ optional($card->partner)->name }}</td>
                    <td class="py-2 text-right font-semibold">{{ number_format((float) $card->gross_amount, 2, ',', '.') }}</td>
                    <td class="py-2 text-center text-default">{{ $card->installments }}x</td>
                    <td class="py-2 text-right text-danger">
                        {{ number_format((float) $card->commission_amount, 2, ',', '.') }}
                        <div class="text-[11px] text-default">%{{ $card->commission_rate }}</div>
                    </td>
                    <td class="py-2 text-right text-success font-semibold">{{ number_format((float) $card->net_amount, 2, ',', '.') }}</td>
                    <td class="py-2 text-default text-[12px]">
                        {{ $card->expected_settlement_date->format('d.m.Y') }}
                        @if ($card->settled_at)
                            <div class="text-success">✓ {{ $card->settled_at->format('d.m.Y') }}</div>
                        @endif
                    </td>
                    <td class="py-2">
                        <span class="text-[11px] px-2 py-0.5 rounded {{ $card->status === 'settled' ? 'bg-success-transparent text-success' : ($card->status === 'cancelled' ? 'bg-danger-transparent text-danger' : 'bg-warning-transparent text-warning') }}">
                            {{ $card->status === 'settled' ? __('Settled') : ($card->status === 'cancelled' ? __('Cancelled') : __('Pending')) }}
                        </span>
                    </td>
                    <td class="py-2 text-right">
                        @if ($card->status === 'pending_settlement')
                            <form method="POST" action="{{ route('app.accounting.card-payments.settle', $card) }}" class="inline">
                                @csrf
                                <button type="submit" class="text-success text-[12px] hover:underline">{{ __('Settle') }}</button>
                            </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="10" class="py-6 text-center text-default">{{ __('No card payments yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div id="card-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4" role="dialog">
    <div class="bg-white rounded-md shadow-xl w-full max-h-[92vh] overflow-hidden" style="max-width: min(540px, calc(100vw - 32px));">
        <form method="POST" action="{{ route('app.accounting.card-payments.store') }}">
            @csrf
            <div class="flex items-center justify-between border-b border-border-color px-4 py-3">
                <h2 class="text-base font-bold text-title inline-flex items-center gap-2"><i class="ph ph-credit-card"></i> {{ __('New Card Payment') }}</h2>
                <button type="button" data-card-close class="text-default hover:text-gray-900 cursor-pointer"><i class="ph ph-x text-lg"></i></button>
            </div>
            <div class="p-4 space-y-3">
                <div>
                    <label class="text-[12px] text-default block mb-1">{{ __('Terminal') }} *</label>
                    <select name="pos_terminal_id" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                        @foreach ($terminals as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="text-[12px] text-default block mb-1">{{ __('Customer') }} *</label>
                    <select name="partner_id" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                        @foreach ($partners as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
                    </select>
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="text-[12px] text-default block mb-1">{{ __('Gross Amount') }} *</label>
                        <input type="number" step="0.01" min="0.01" name="gross_amount" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0 text-right">
                    </div>
                    <div>
                        <label class="text-[12px] text-default block mb-1">{{ __('Installments') }} *</label>
                        <input type="number" min="1" max="36" name="installments" value="1" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0 text-right">
                    </div>
                    <div>
                        <label class="text-[12px] text-default block mb-1">{{ __('Date') }} *</label>
                        <input type="date" name="transaction_date" value="{{ now()->toDateString() }}" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                    </div>
                </div>
            </div>
            <div class="border-t border-border-color px-4 py-3 flex items-center justify-end gap-2">
                <button type="button" data-card-close class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light cursor-pointer">{{ __('Cancel') }}</button>
                <button type="submit" class="btn-sm bg-primary text-white border border-primary hover:bg-primary/90 cursor-pointer inline-flex items-center gap-2"><i class="ph ph-check"></i> {{ __('Save') }}</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const modal = document.getElementById('card-modal');
    const open = () => { modal.classList.remove('hidden'); modal.classList.add('flex'); };
    const close = () => { modal.classList.remove('flex'); modal.classList.add('hidden'); };
    document.querySelectorAll('[data-card-open-add]').forEach((b) => b.addEventListener('click', open));
    document.querySelectorAll('[data-card-close]').forEach((b) => b.addEventListener('click', close));
    modal.addEventListener('click', (e) => { if (e.target === modal) close(); });
})();
</script>
@endsection
