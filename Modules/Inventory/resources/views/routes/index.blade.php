@extends('app.layouts.app')

@section('title', __('Routes'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <h1 class="text-gray-900 text-xl font-bold mb-0">{{ __('Routes') }}</h1>
    <button type="button" data-hs-overlay="#add-route-modal" class="btn-sm bg-dark text-white border border-dark inline-flex items-center gap-2 hover:bg-primary-hover hover:border-primary-hover cursor-pointer">
        <i class="ph ph-plus"></i> {{ __('New Route') }}
    </button>
</div>

<div class="bg-white border border-border-color rounded-md">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-sm text-default border-b border-border-color">
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Name') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Rule Count') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($routes as $route)
                    <tr class="border-b border-border-color">
                        <td class="py-2.5 px-3 text-sm font-semibold text-title">{{ $route->name }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $route->rules_count }}</td>
                        <td class="py-2.5 px-3">
                            <a href="{{ route('app.inventory.routes.show', $route) }}" class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light cursor-pointer">{{ __('View') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="py-8 text-center text-sm text-default">{{ __('No routes yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@include('inventory::routes._form-modal')
@endsection
