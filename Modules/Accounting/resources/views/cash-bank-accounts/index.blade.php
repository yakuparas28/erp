@extends('app.layouts.app')

@section('title', __('Cash & Bank Accounts'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <div>
        <h1 class="text-gray-900 text-xl max-lg:text-lg font-bold mb-0">{{ __('Cash & Bank Accounts') }}</h1>
        <p class="text-[12px] text-default mb-0 mt-1">
            {{ __('Define every cash box and bank account. Payments post to the linked chart-of-account.') }}
        </p>
    </div>
    <button type="button" data-cb-open-add class="btn-sm bg-primary text-white border border-primary hover:bg-primary/90 cursor-pointer inline-flex items-center gap-2">
        <i class="ph ph-plus"></i> {{ __('Add Account') }}
    </button>
</div>

@if (session('status'))
    <div class="bg-success-transparent text-success border border-success rounded-md px-4 py-3 text-sm mb-4">{{ session('status') }}</div>
@endif

<div class="bg-white border border-border-color rounded-md p-4">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-default text-[11px] uppercase font-medium">
                    <th class="text-left py-2 border-b border-border-color">{{ __('Type') }}</th>
                    <th class="text-left py-2 border-b border-border-color">{{ __('Name') }}</th>
                    <th class="text-left py-2 border-b border-border-color">{{ __('Code') }}</th>
                    <th class="text-left py-2 border-b border-border-color">{{ __('Bank / IBAN') }}</th>
                    <th class="text-left py-2 border-b border-border-color">{{ __('Currency') }}</th>
                    <th class="text-left py-2 border-b border-border-color">{{ __('COA') }}</th>
                    <th class="text-right py-2 border-b border-border-color">{{ __('Opening Balance') }}</th>
                    <th class="text-left py-2 border-b border-border-color">{{ __('Status') }}</th>
                    <th class="py-2 border-b border-border-color"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($accounts as $account)
                <tr class="border-b border-border-color">
                    <td class="py-2">
                        <span class="text-[11px] px-2 py-0.5 rounded {{ $account->type === 'cash' ? 'bg-info-transparent text-info' : 'bg-warning-transparent text-warning' }}">
                            {{ $account->type === 'cash' ? __('Cash') : __('Bank') }}
                        </span>
                    </td>
                    <td class="py-2 font-medium">
                        <a href="{{ route('app.accounting.cash-bank-accounts.statement', $account) }}" class="text-primary hover:underline">{{ $account->name }}</a>
                    </td>
                    <td class="py-2 text-default font-mono">{{ $account->code ?: '—' }}</td>
                    <td class="py-2 text-default">
                        @if ($account->type === 'bank')
                            <div class="text-gray-900">{{ $account->bank_name ?: '—' }}</div>
                            <div class="text-[11px] font-mono">{{ $account->iban ?: $account->account_no ?: '' }}</div>
                        @else
                            —
                        @endif
                    </td>
                    <td class="py-2 text-default">{{ optional($account->currency)->code ?? 'TRY' }}</td>
                    <td class="py-2 text-default font-mono">{{ optional($account->chartOfAccount)->code ?? '—' }}</td>
                    <td class="py-2 text-right text-default">{{ number_format((float) $account->opening_balance, 2, ',', '.') }}</td>
                    <td class="py-2">
                        <span class="text-[11px] px-2 py-0.5 rounded {{ $account->is_active ? 'bg-success-transparent text-success' : 'bg-light text-default' }}">
                            {{ $account->is_active ? __('Active') : __('Archived') }}
                        </span>
                    </td>
                    <td class="py-2 text-right">
                        <button type="button" data-cb-edit data-account="{{ json_encode($account) }}" class="text-primary text-[12px] hover:underline">{{ __('Edit') }}</button>
                        <form method="POST" action="{{ route('app.accounting.cash-bank-accounts.destroy', $account) }}" class="inline" onsubmit="return confirm('{{ __('Delete or archive this account?') }}')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-danger text-[12px] hover:underline ms-2">{{ __('Delete') }}</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="9" class="py-6 text-center text-default">{{ __('No cash or bank accounts yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@include('accounting::cash-bank-accounts._form-modal', [
    'currencies' => $currencies,
    'cashAccounts' => $cashAccounts,
    'bankAccounts' => $bankAccounts,
])
@endsection
