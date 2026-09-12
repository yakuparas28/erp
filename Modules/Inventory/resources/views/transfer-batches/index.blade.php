@extends('app.layouts.app')

@section('title', __('Batch Transfers'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <div>
        <h1 class="text-gray-900 text-xl font-bold mb-1">{{ __('Batch Transfers') }}</h1>
        <p class="text-sm text-default mb-0">{{ __('Group multiple draft transfers and complete them together.') }}</p>
    </div>
    <button type="button" data-hs-overlay="#add-batch-modal" class="btn-sm bg-dark text-white border border-dark inline-flex items-center gap-2 hover:bg-primary-hover hover:border-primary-hover cursor-pointer">
        <i class="ph ph-plus"></i> {{ __('New Batch') }}
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
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Name') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Status') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Transfers') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($batches as $batch)
                    <tr class="border-b border-border-color">
                        <td class="py-2.5 px-3 text-sm font-semibold text-title">{{ $batch->name }}</td>
                        <td class="py-2.5 px-3">
                            <span class="text-[11px] {{ ['draft'=>'bg-default-transparent text-default','in_progress'=>'bg-warning-transparent text-warning','done'=>'bg-success-transparent text-success','cancelled'=>'bg-danger-transparent text-danger'][$batch->status] }} px-2 py-0.5 rounded">
                                {{ __('batch-status.'.$batch->status) }}
                            </span>
                        </td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $batch->transfers->count() }}</td>
                        <td class="py-2.5 px-3">
                            <a href="{{ route('app.inventory.transfer-batches.show', $batch) }}" class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light inline-flex items-center gap-1">
                                {{ __('View') }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-8 text-center text-sm text-default">{{ __('No batches yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div id="add-batch-modal" class="hs-overlay hidden size-full fixed top-0 start-0 z-[80] overflow-x-hidden overflow-y-auto pointer-events-none">
    <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-lg sm:w-full m-3 sm:mx-auto pointer-events-auto min-h-screen flex items-center justify-center">
        <form method="POST" action="{{ route('app.inventory.transfer-batches.store') }}" class="flex flex-col bg-white border shadow-sm rounded-md border-border-color w-full">
            @csrf
            <div class="flex justify-between items-center p-4 border-b border-border-color">
                <h2 class="text-base font-bold text-title">{{ __('New Batch') }}</h2>
                <button type="button" class="size-7 inline-flex justify-center items-center rounded-md border border-border-color hover:bg-light cursor-pointer" data-hs-overlay="#add-batch-modal" aria-label="{{ __('Cancel') }}"><i class="ph ph-x text-sm"></i></button>
            </div>
            <div class="p-4">
                <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Name') }} <span class="text-danger">*</span></label>
                <input type="text" name="name" required placeholder="{{ __('e.g. Sabah dağıtımı') }}" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
            </div>
            <div class="flex justify-end gap-2 p-4 border-t border-border-color">
                <button type="button" class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light cursor-pointer" data-hs-overlay="#add-batch-modal">{{ __('Cancel') }}</button>
                <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Save') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
