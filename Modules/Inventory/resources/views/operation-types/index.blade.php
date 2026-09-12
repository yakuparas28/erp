@extends('app.layouts.app')

@section('title', __('Operation Types'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <div>
        <h1 class="text-gray-900 text-xl font-bold mb-1">{{ __('Operation Types') }}</h1>
        <p class="text-sm text-default mb-0">{{ __('Named picking types per warehouse (Receipts, Deliveries, Internal, Scrap) with default source/destination and sequence prefixes.') }}</p>
    </div>
    <button type="button" data-hs-overlay="#add-operation-type-modal" class="btn-sm bg-dark text-white border border-dark inline-flex items-center gap-2 hover:bg-primary-hover hover:border-primary-hover cursor-pointer">
        <i class="ph ph-plus"></i> {{ __('New Operation Type') }}
    </button>
</div>

@if (session('status'))
    <div class="bg-success-transparent text-success border border-success rounded-md px-4 py-3 text-sm mb-4">{{ session('status') }}</div>
@endif

<div class="bg-white border border-border-color rounded-md">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-sm text-default border-b border-border-color">
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Code') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Name') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Warehouse') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Type') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Prefix') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($types as $type)
                    <tr class="border-b border-border-color">
                        <td class="py-2.5 px-3 text-sm font-semibold text-title font-mono">{{ $type->code }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $type->name }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $type->warehouse->name }}</td>
                        <td class="py-2.5 px-3">
                            <span class="text-[11px] {{ ['incoming'=>'bg-success-transparent text-success','outgoing'=>'bg-warning-transparent text-warning','internal'=>'bg-info-transparent text-info','scrap'=>'bg-danger-transparent text-danger'][$type->type] }} px-2 py-0.5 rounded">
                                {{ __('operation-type.'.$type->type) }}
                            </span>
                        </td>
                        <td class="py-2.5 px-3 text-sm text-default font-mono">{{ $type->sequence_prefix ?: '—' }}</td>
                        <td class="py-2.5 px-3">
                            <div class="flex items-center gap-2">
                                <button type="button" data-hs-overlay="#edit-operation-type-modal-{{ $type->id }}" class="size-7 rounded-md border border-border-color flex items-center justify-center text-gray-900 hover:bg-light cursor-pointer" title="{{ __('Edit') }}">
                                    <i class="ph ph-pencil-simple text-xs"></i>
                                </button>
                                <form method="POST" action="{{ route('app.inventory.operation-types.destroy', $type) }}" onsubmit="return confirm('{{ __('Delete this operation type?') }}')">
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
                    <tr><td colspan="6" class="py-8 text-center text-sm text-default">{{ __('No operation types yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@include('inventory::operation-types._form-modal', ['id' => 'add-operation-type-modal', 'action' => route('app.inventory.operation-types.store'), 'title' => __('New Operation Type'), 'type' => null, 'warehouses' => $warehouses, 'locations' => $locations])

@foreach ($types as $t)
    @include('inventory::operation-types._form-modal', ['id' => 'edit-operation-type-modal-'.$t->id, 'action' => route('app.inventory.operation-types.update', $t), 'title' => __('Edit Operation Type'), 'type' => $t, 'warehouses' => $warehouses, 'locations' => $locations, 'method' => 'PATCH'])
@endforeach
@endsection
