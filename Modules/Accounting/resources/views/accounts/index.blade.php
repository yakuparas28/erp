@extends('app.layouts.app')

@section('title', __('Chart of Accounts'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <h1 class="text-gray-900 text-xl font-bold mb-0">{{ __('Chart of Accounts') }}</h1>
    <button type="button" data-hs-overlay="#add-account-modal" class="btn-sm bg-dark text-white border border-dark inline-flex items-center gap-2 hover:bg-primary-hover hover:border-primary-hover cursor-pointer">
        <i class="ph ph-plus"></i> {{ __('New Account') }}
    </button>
</div>

<div class="bg-white border border-border-color rounded-md">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-sm text-default border-b border-border-color">
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Code') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Name') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Type') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($accounts as $account)
                    <tr class="border-b border-border-color">
                        <td class="py-2.5 px-3 text-sm font-semibold text-title">{{ $account->code }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $account->name }}</td>
                        <td class="py-2.5 px-3">
                            <span class="text-[11px] {{ [
                                'asset' => 'bg-success-transparent text-success',
                                'liability' => 'bg-danger-transparent text-danger',
                                'equity' => 'bg-info-transparent text-info',
                                'income' => 'bg-primary-transparent text-primary',
                                'expense' => 'bg-warning-transparent text-warning',
                            ][$account->type] }} px-2 py-0.5 rounded">
                                {{ __('account-type.'.$account->type) }}
                            </span>
                        </td>
                        <td class="py-2.5 px-3">
                            <form method="POST" action="{{ route('app.accounting.accounts.destroy', $account) }}" onsubmit="return confirm('{{ __('Delete this account?') }}')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="size-7 rounded-md border border-border-color flex items-center justify-center text-danger hover:bg-light cursor-pointer" title="{{ __('Delete') }}">
                                    <i class="ph ph-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-8 text-center text-sm text-default">{{ __('No accounts yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@include('accounting::accounts._form-modal', ['id' => 'add-account-modal', 'action' => route('app.accounting.accounts.store'), 'title' => __('New Account')])
@endsection
