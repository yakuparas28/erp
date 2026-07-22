@extends('app.layouts.app')

@section('title', __('Putaway Rules'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <h1 class="text-gray-900 text-xl font-bold mb-0">{{ __('Putaway Rules') }}</h1>
    <button type="button" data-hs-overlay="#add-putaway-rule-modal" class="btn-sm bg-dark text-white border border-dark inline-flex items-center gap-2 hover:bg-primary-hover hover:border-primary-hover cursor-pointer">
        <i class="ph ph-plus"></i> {{ __('New Rule') }}
    </button>
</div>

<div class="bg-white border border-border-color rounded-md">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-sm text-default border-b border-border-color">
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Product / Category') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Source') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Destination') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Sequence') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rules as $rule)
                    <tr class="border-b border-border-color">
                        <td class="py-2.5 px-3 text-sm font-semibold text-title">{{ $rule->product?->name ?? $rule->productCategory?->name }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $rule->sourceLocation->name }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $rule->destLocation->name }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $rule->sequence }}</td>
                        <td class="py-2.5 px-3">
                            <form method="POST" action="{{ route('app.inventory.putaway.destroy', $rule) }}" onsubmit="return confirm('{{ __('Delete this putaway rule?') }}')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="size-7 rounded-md border border-border-color flex items-center justify-center text-danger hover:bg-light cursor-pointer" title="{{ __('Delete') }}">
                                    <i class="ph ph-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-sm text-default">{{ __('No putaway rules yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@include('inventory::putaway._form-modal')
@endsection
