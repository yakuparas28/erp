@extends('app.layouts.app')

@section('title', __('Stock Counts'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <h1 class="text-gray-900 text-xl font-bold mb-0">{{ __('Stock Counts') }}</h1>
    <button type="button" data-hs-overlay="#add-adjustment-modal" class="btn-sm bg-dark text-white border border-dark inline-flex items-center gap-2 hover:bg-primary-hover hover:border-primary-hover cursor-pointer">
        <i class="ph ph-plus"></i> {{ __('Start Stock Count') }}
    </button>
</div>

<div class="bg-white border border-border-color rounded-md">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-sm text-default border-b border-border-color">
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Location') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Status') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Started By') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Created') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($adjustments as $adjustment)
                    <tr class="border-b border-border-color">
                        <td class="py-2.5 px-3 text-sm font-semibold text-title">{{ $adjustment->location->name }}</td>
                        <td class="py-2.5 px-3">
                            <span class="text-[11px] {{ $adjustment->status === 'approved' ? 'bg-success-transparent text-success' : ($adjustment->status === 'cancelled' ? 'bg-light text-default' : 'bg-warning-transparent text-warning') }} px-2 py-0.5 rounded">
                                {{ __('adjustment-status.'.$adjustment->status) }}
                            </span>
                        </td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $adjustment->creator->name }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $adjustment->created_at->format('d.m.Y H:i') }}</td>
                        <td class="py-2.5 px-3">
                            <a href="{{ route('app.inventory.adjustments.show', $adjustment) }}" class="btn-sm bg-white border border-border-color text-gray-900 inline-flex items-center gap-2 hover:bg-light">
                                {{ __('Open') }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-sm text-default">{{ __('No stock counts yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div id="add-adjustment-modal" class="hs-overlay hidden size-full fixed top-0 start-0 z-[80] overflow-x-hidden overflow-y-auto pointer-events-none">
    <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-lg sm:w-full m-3 sm:mx-auto pointer-events-auto min-h-screen flex items-center justify-center">
        <form method="POST" action="{{ route('app.inventory.adjustments.store') }}" class="flex flex-col bg-white border shadow-sm rounded-md border-border-color w-full">
            @csrf
            <div class="flex justify-between items-center p-4 border-b border-border-color">
                <h2 class="text-base font-bold text-title">{{ __('Start Stock Count') }}</h2>
                <button type="button" class="size-7 inline-flex justify-center items-center rounded-md border border-border-color hover:bg-light cursor-pointer" data-hs-overlay="#add-adjustment-modal" aria-label="{{ __('Cancel') }}"><i class="ph ph-x text-sm"></i></button>
            </div>
            <div class="p-4">
                <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Location') }} <span class="text-danger">*</span></label>
                <select name="location_id" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                    @foreach ($locations as $location)
                        <option value="{{ $location->id }}" @disabled($location->counting_lock)>
                            {{ $location->name }} @if ($location->counting_lock) ({{ __('locked') }}) @endif
                        </option>
                    @endforeach
                </select>
                <p class="text-[11px] text-default mt-2 mb-0">{{ __('The location will be locked as soon as counting starts; other stock moves are blocked until approval or cancellation.') }}</p>
            </div>
            <div class="flex justify-end gap-2 p-4 border-t border-border-color">
                <button type="button" class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light cursor-pointer" data-hs-overlay="#add-adjustment-modal">{{ __('Cancel') }}</button>
                <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Start') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
