@extends('app.layouts.app')

@section('title', $route->name)

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <h1 class="text-gray-900 text-xl font-bold mb-0">{{ $route->name }}</h1>
    <a href="{{ route('app.inventory.routes.index') }}" class="btn-sm bg-white border border-border-color text-gray-900 inline-flex items-center gap-2 hover:bg-light">
        <i class="ph ph-arrow-left"></i> {{ __('Back to List') }}
    </a>
</div>

<div class="bg-white border border-border-color rounded-md mb-6">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-sm text-default border-b border-border-color">
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Sequence') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Source') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Destination') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Action') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($route->rules as $rule)
                    <tr class="border-b border-border-color">
                        <td class="py-2.5 px-3 text-sm text-default">{{ $rule->sequence }}</td>
                        <td class="py-2.5 px-3 text-sm font-semibold text-title">{{ $rule->fromLocation?->name }}</td>
                        <td class="py-2.5 px-3 text-sm font-semibold text-title">{{ $rule->toLocation?->name }}</td>
                        <td class="py-2.5 px-3">
                            <span class="text-[11px] {{ $rule->action === 'push' ? 'bg-info-transparent text-info' : 'bg-warning-transparent text-warning' }} px-2 py-0.5 rounded">
                                {{ $rule->action === 'push' ? __('Push') : __('Pull') }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-8 text-center text-sm text-default">{{ __('No route rules yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white border border-border-color rounded-md p-4">
        <h2 class="text-base font-bold text-title mb-3">{{ __('Add Rule') }}</h2>
        <form method="POST" action="{{ route('app.inventory.routes.rules.store', $route) }}" class="grid grid-cols-12 gap-3">
            @csrf
            <div class="col-span-12">
                <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Source') }} <span class="text-danger">*</span></label>
                <select name="from_location_id" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                    <option value="">—</option>
                    @foreach ($locations as $location)
                        <option value="{{ $location->id }}">{{ $location->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-span-12">
                <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Destination') }} <span class="text-danger">*</span></label>
                <select name="to_location_id" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                    <option value="">—</option>
                    @foreach ($locations as $location)
                        <option value="{{ $location->id }}">{{ $location->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-span-12 sm:col-span-6">
                <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Action') }} <span class="text-danger">*</span></label>
                <select name="action" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                    <option value="push">{{ __('Push') }}</option>
                    <option value="pull">{{ __('Pull') }}</option>
                </select>
            </div>
            <div class="col-span-12 sm:col-span-6">
                <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Sequence') }} <span class="text-danger">*</span></label>
                <input type="number" min="0" step="1" name="sequence" required value="0" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
            </div>
            <div class="col-span-12">
                <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Save') }}</button>
            </div>
        </form>
    </div>

    <div class="bg-white border border-border-color rounded-md p-4">
        <h2 class="text-base font-bold text-title mb-3">{{ __('Run Push Route') }}</h2>
        <form method="POST" action="{{ route('app.inventory.routes.execute', $route) }}" class="grid grid-cols-12 gap-3">
            @csrf
            <div class="col-span-12">
                <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Product') }} <span class="text-danger">*</span></label>
                <select name="product_id" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                    <option value="">—</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}">{{ $product->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-span-12 sm:col-span-6">
                <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Unit') }} <span class="text-danger">*</span></label>
                <select name="uom_id" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                    <option value="">—</option>
                    @foreach ($uoms as $uom)
                        <option value="{{ $uom->id }}">{{ $uom->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-span-12 sm:col-span-6">
                <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Quantity') }} <span class="text-danger">*</span></label>
                <input type="number" step="0.0001" min="0.0001" name="qty" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
            </div>
            <div class="col-span-12">
                <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Run') }}</button>
            </div>
            <p class="col-span-12 text-[11px] text-default mb-0">{{ __('This only chains the push steps of this route for testing/manual triggering purposes.') }}</p>
        </form>
    </div>
</div>
@endsection
