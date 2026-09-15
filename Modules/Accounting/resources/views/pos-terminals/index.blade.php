@extends('app.layouts.app')

@section('title', __('POS Terminals'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <div>
        <h1 class="text-gray-900 text-xl max-lg:text-lg font-bold mb-0">{{ __('POS Terminals') }}</h1>
        <p class="text-[12px] text-default mb-0 mt-1">
            {{ __('Every POS/virtual terminal, per bank. Commission per installment; net amount posts to the linked bank when settled.') }}
        </p>
    </div>
    <button type="button" data-pos-open-add class="btn-sm bg-primary text-white border border-primary hover:bg-primary/90 cursor-pointer inline-flex items-center gap-2">
        <i class="ph ph-plus"></i> {{ __('Add Terminal') }}
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
                    <th class="text-left py-2 border-b border-border-color">{{ __('Terminal') }}</th>
                    <th class="text-left py-2 border-b border-border-color">{{ __('Bank Account') }}</th>
                    <th class="text-left py-2 border-b border-border-color">{{ __('Commission Rates') }}</th>
                    <th class="text-right py-2 border-b border-border-color">{{ __('Valör') }}</th>
                    <th class="text-left py-2 border-b border-border-color">{{ __('Status') }}</th>
                    <th class="py-2 border-b border-border-color"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($terminals as $terminal)
                <tr class="border-b border-border-color">
                    <td class="py-2 font-medium text-gray-900">{{ $terminal->name }}</td>
                    <td class="py-2 text-default">{{ optional($terminal->bankJournal)->name ?? '—' }}</td>
                    <td class="py-2 text-default font-mono text-[11px]">
                        @foreach ($terminal->commission_rates ?? [] as $k => $v)
                            <span class="px-1.5 py-0.5 bg-light rounded me-1">{{ $k }}x → %{{ $v }}</span>
                        @endforeach
                    </td>
                    <td class="py-2 text-right text-default">+{{ $terminal->settlement_days }} {{ __('day') }}</td>
                    <td class="py-2">
                        <span class="text-[11px] px-2 py-0.5 rounded {{ $terminal->is_active ? 'bg-success-transparent text-success' : 'bg-light text-default' }}">
                            {{ $terminal->is_active ? __('Active') : __('Archived') }}
                        </span>
                    </td>
                    <td class="py-2 text-right">
                        <button type="button" data-pos-edit data-terminal="{{ json_encode($terminal) }}" class="text-primary text-[12px] hover:underline">{{ __('Edit') }}</button>
                        <form method="POST" action="{{ route('app.accounting.pos-terminals.destroy', $terminal) }}" class="inline" onsubmit="return confirm('{{ __('Delete this terminal?') }}')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-danger text-[12px] hover:underline ms-2">{{ __('Delete') }}</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="py-6 text-center text-default">{{ __('No POS terminals yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@include('accounting::pos-terminals._form-modal', ['bankJournals' => $bankJournals])
@endsection
