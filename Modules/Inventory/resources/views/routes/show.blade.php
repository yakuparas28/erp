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
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Name') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Source') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Destination') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Action') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Procure Method') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($route->rules as $rule)
                    @php
                        $actionColors = ['push'=>'bg-info-transparent text-info','pull'=>'bg-warning-transparent text-warning','manufacture'=>'bg-primary-transparent text-primary','buy'=>'bg-success-transparent text-success'];
                    @endphp
                    <tr class="border-b border-border-color">
                        <td class="py-2.5 px-3 text-sm text-default">{{ $rule->sequence }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $rule->name ?: '—' }}</td>
                        <td class="py-2.5 px-3 text-sm text-title">{{ $rule->fromLocation?->name }}</td>
                        <td class="py-2.5 px-3 text-sm text-title">{{ $rule->toLocation?->name }}</td>
                        <td class="py-2.5 px-3">
                            <span class="text-[11px] {{ $actionColors[$rule->action] ?? 'bg-default-transparent text-default' }} px-2 py-0.5 rounded">
                                {{ __('rule-action.'.$rule->action) }}
                            </span>
                        </td>
                        <td class="py-2.5 px-3 text-xs text-default">{{ __('procure-method.'.$rule->procure_method) }}</td>
                        <td class="py-2.5 px-3">
                            <div class="flex items-center gap-2">
                                <button type="button" data-hs-overlay="#edit-rule-modal-{{ $rule->id }}" class="size-7 rounded-md border border-border-color flex items-center justify-center text-gray-900 hover:bg-light cursor-pointer" title="{{ __('Edit') }}">
                                    <i class="ph ph-pencil-simple text-xs"></i>
                                </button>
                                <form method="POST" action="{{ route('app.inventory.routes.rules.destroy', $rule) }}" onsubmit="return confirm('{{ __('Delete this rule?') }}')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="size-7 rounded-md border border-border-color flex items-center justify-center text-danger hover:bg-light cursor-pointer" title="{{ __('Delete') }}">
                                        <i class="ph ph-trash text-xs"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-8 text-center text-sm text-default">{{ __('No route rules yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@foreach ($route->rules as $rule)
    <div id="edit-rule-modal-{{ $rule->id }}" class="hs-overlay hidden size-full fixed top-0 start-0 z-[80] overflow-x-hidden overflow-y-auto pointer-events-none">
        <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-lg sm:w-full m-3 sm:mx-auto pointer-events-auto min-h-screen flex items-center justify-center">
            <form method="POST" action="{{ route('app.inventory.routes.rules.update', $rule) }}" class="flex flex-col bg-white border shadow-sm rounded-md border-border-color w-full">
                @csrf
                @method('PATCH')
                <div class="flex justify-between items-center p-4 border-b border-border-color">
                    <h2 class="text-base font-bold text-title">{{ __('Edit Rule') }}</h2>
                    <button type="button" class="size-7 inline-flex justify-center items-center rounded-md border border-border-color hover:bg-light cursor-pointer" data-hs-overlay="#edit-rule-modal-{{ $rule->id }}" aria-label="{{ __('Cancel') }}"><i class="ph ph-x text-sm"></i></button>
                </div>
                <div class="p-4 grid grid-cols-12 gap-3">
                    <div class="col-span-12">
                        <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Name') }}</label>
                        <input type="text" name="name" value="{{ $rule->name }}" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                    </div>
                    <div class="col-span-6">
                        <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Source') }}</label>
                        <select name="from_location_id" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                            @foreach ($locations as $loc)
                                <option value="{{ $loc->id }}" @selected($rule->from_location_id === $loc->id)>{{ $loc->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-span-6">
                        <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Destination') }}</label>
                        <select name="to_location_id" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                            @foreach ($locations as $loc)
                                <option value="{{ $loc->id }}" @selected($rule->to_location_id === $loc->id)>{{ $loc->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-span-6">
                        <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Action') }}</label>
                        <select name="action" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                            @foreach (['push', 'pull', 'manufacture', 'buy'] as $a)
                                <option value="{{ $a }}" @selected($rule->action === $a)>{{ __('rule-action.'.$a) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-span-6">
                        <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Procure Method') }}</label>
                        <select name="procure_method" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                            @foreach (['make_to_stock', 'make_to_order'] as $pm)
                                <option value="{{ $pm }}" @selected($rule->procure_method === $pm)>{{ __('procure-method.'.$pm) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-span-12">
                        <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Sequence') }}</label>
                        <input type="number" min="0" step="1" name="sequence" value="{{ $rule->sequence }}" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                    </div>
                </div>
                <div class="flex justify-end gap-2 p-4 border-t border-border-color">
                    <button type="button" class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light cursor-pointer" data-hs-overlay="#edit-rule-modal-{{ $rule->id }}">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Save') }}</button>
                </div>
            </form>
        </div>
    </div>
@endforeach

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
            <div class="col-span-12">
                <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Name') }}</label>
                <input type="text" name="name" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
            </div>
            <div class="col-span-12 sm:col-span-6">
                <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Action') }} <span class="text-danger">*</span></label>
                <select name="action" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                    @foreach (['push', 'pull', 'manufacture', 'buy'] as $a)
                        <option value="{{ $a }}">{{ __('rule-action.'.$a) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-span-12 sm:col-span-6">
                <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Procure Method') }} <span class="text-danger">*</span></label>
                <select name="procure_method" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                    @foreach (['make_to_stock', 'make_to_order'] as $pm)
                        <option value="{{ $pm }}">{{ __('procure-method.'.$pm) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-span-12">
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
