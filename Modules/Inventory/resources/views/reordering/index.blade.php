@extends('app.layouts.app')

@section('title', __('Reordering Rules'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <h1 class="text-gray-900 text-xl font-bold mb-0">{{ __('Reordering Rules') }}</h1>
    <button type="button" data-hs-overlay="#add-reordering-rule-modal" class="btn-sm bg-dark text-white border border-dark inline-flex items-center gap-2 hover:bg-primary-hover hover:border-primary-hover cursor-pointer">
        <i class="ph ph-plus"></i> {{ __('New Rule') }}
    </button>
</div>

<div class="bg-white border border-border-color rounded-md mb-6">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-sm text-default border-b border-border-color">
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Product') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Location') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Min Qty') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Max Qty') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Trigger Type') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rules as $rule)
                    <tr class="border-b border-border-color">
                        <td class="py-2.5 px-3 text-sm font-semibold text-title">{{ $rule->product?->name }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $rule->location?->name }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $rule->min_qty }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $rule->max_qty }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $rule->trigger_type === 'auto' ? __('Auto') : __('Manual') }}</td>
                        <td class="py-2.5 px-3">
                            <form method="POST" action="{{ route('app.inventory.reordering.destroy', $rule) }}" onsubmit="return confirm('{{ __('Delete this reordering rule?') }}')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="size-7 rounded-md border border-border-color flex items-center justify-center text-danger hover:bg-light cursor-pointer" title="{{ __('Delete') }}">
                                    <i class="ph ph-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-sm text-default">{{ __('No reordering rules yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<h2 class="text-gray-900 text-lg font-bold mb-3">{{ __('Pending Suggestions') }}</h2>

<div class="bg-white border border-border-color rounded-md">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-sm text-default border-b border-border-color">
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Product') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Suggested Qty') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($suggestions as $suggestion)
                    <tr class="border-b border-border-color">
                        <td class="py-2.5 px-3 text-sm font-semibold text-title">{{ $suggestion->reorderingRule?->product?->name }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $suggestion->suggested_qty }}</td>
                        <td class="py-2.5 px-3">
                            <form method="POST" action="{{ route('app.inventory.reordering.suggestions.acknowledge', $suggestion) }}" onsubmit="return confirm('{{ __('Acknowledge this suggestion?') }}')">
                                @csrf
                                <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Acknowledge') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="py-8 text-center text-sm text-default">{{ __('No pending suggestions.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@include('inventory::reordering._form-modal')
@endsection
