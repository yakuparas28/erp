@extends('app.layouts.app')

@section('title', __('Currencies'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <h1 class="text-gray-900 text-xl font-bold mb-0">{{ __('Currencies') }}</h1>
    <button type="button" data-hs-overlay="#add-currency-modal" class="btn-sm bg-dark text-white border border-dark inline-flex items-center gap-2 hover:bg-primary-hover hover:border-primary-hover cursor-pointer">
        <i class="ph ph-plus"></i> {{ __('New Currency') }}
    </button>
</div>

@error('currency')
    <div class="bg-danger-transparent text-danger border border-danger rounded-md px-4 py-3 text-sm mb-4">{{ $message }}</div>
@enderror

<div class="bg-white border border-border-color rounded-md">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-sm text-default border-b border-border-color">
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Code') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Symbol') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Name') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Decimal Places') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Type') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Status') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($currencies as $currency)
                    <tr class="border-b border-border-color {{ $currency->active ? '' : 'opacity-60' }}">
                        <td class="py-2.5 px-3 text-sm font-semibold text-title">{{ $currency->code }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $currency->symbol }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">
                            <a href="{{ route('app.accounting.currencies.show', $currency) }}" class="hover:underline">{{ $currency->name }}</a>
                        </td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $currency->decimal_places }}</td>
                        <td class="py-2.5 px-3">
                            @if ($currency->is_functional)
                                <span class="text-[11px] bg-info-transparent text-info px-2 py-0.5 rounded">{{ __('Functional') }}</span>
                            @else
                                <span class="text-[11px] bg-warning-transparent text-warning px-2 py-0.5 rounded">{{ __('Foreign') }}</span>
                            @endif
                        </td>
                        <td class="py-2.5 px-3">
                            @if ($currency->active)
                                <span class="text-[11px] bg-success-transparent text-success px-2 py-0.5 rounded">{{ __('Active') }}</span>
                            @else
                                <span class="text-[11px] bg-default-transparent text-default px-2 py-0.5 rounded">{{ __('Archived') }}</span>
                            @endif
                        </td>
                        <td class="py-2.5 px-3">
                            <div class="flex items-center gap-2">
                                <button type="button" data-hs-overlay="#edit-currency-modal-{{ $currency->id }}" class="size-7 rounded-md border border-border-color flex items-center justify-center text-gray-900 hover:bg-light cursor-pointer" title="{{ __('Edit') }}">
                                    <i class="ph ph-pencil-simple"></i>
                                </button>
                                @unless ($currency->is_functional)
                                    @if ($currency->active)
                                        <form method="POST" action="{{ route('app.accounting.currencies.archive', $currency) }}">
                                            @csrf
                                            <button type="submit" class="size-7 rounded-md border border-border-color flex items-center justify-center text-warning hover:bg-light cursor-pointer" title="{{ __('Archive') }}">
                                                <i class="ph ph-archive"></i>
                                            </button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('app.accounting.currencies.restore', $currency) }}">
                                            @csrf
                                            <button type="submit" class="size-7 rounded-md border border-border-color flex items-center justify-center text-success hover:bg-light cursor-pointer" title="{{ __('Restore') }}">
                                                <i class="ph ph-arrow-counter-clockwise"></i>
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('app.accounting.currencies.destroy', $currency) }}" onsubmit="return confirm('{{ __('Delete this currency?') }}')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="size-7 rounded-md border border-border-color flex items-center justify-center text-danger hover:bg-light cursor-pointer" title="{{ __('Delete') }}">
                                                <i class="ph ph-trash"></i>
                                            </button>
                                        </form>
                                    @endif
                                @endunless
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-8 text-center text-sm text-default">{{ __('No currencies yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@include('accounting::currencies._create-modal')

@foreach ($currencies as $currency)
    @include('accounting::currencies._edit-modal', ['currency' => $currency])
@endforeach
@endsection
