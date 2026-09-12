@extends('app.layouts.app')

@section('title', __('Leave Configuration'))

@section('content')
<div class="mb-3 lg:mb-6">
    <h1 class="text-gray-900 text-xl font-bold mb-1">{{ __('Leave Configuration') }}</h1>
    <p class="text-sm text-default mb-0">{{ __('Holidays, critical periods, and hourly leave settings.') }}</p>
</div>

@if (session('status'))
    <div class="bg-success-transparent text-success border border-success rounded-md px-4 py-3 text-sm mb-4">{{ session('status') }}</div>
@endif
@if ($errors->any())
    <div class="bg-danger-transparent text-danger border border-danger rounded-md px-4 py-3 text-sm mb-4">
        @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
    </div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-2 gap-3 items-start">

    <div class="bg-white border border-border-color rounded-md">
        <div class="flex items-center justify-between p-4 border-b border-border-color">
            <h2 class="text-base font-bold text-title mb-0">{{ __('Holidays') }}</h2>
            <button type="button" data-hs-overlay="#add-holiday-modal" class="btn-sm bg-dark text-white border border-dark inline-flex items-center gap-2 hover:bg-primary-hover cursor-pointer">
                <i class="ph ph-plus"></i> {{ __('Add') }}
            </button>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-sm text-default border-b border-border-color">
                        <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Date') }}</th>
                        <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Holiday name') }}</th>
                        <th class="text-right py-2 px-3 font-semibold text-gray-900">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($holidays as $h)
                        <tr class="border-b border-border-color">
                            <td class="py-2.5 px-3 font-mono text-sm">{{ $h->date->format('d.m.Y') }}</td>
                            <td class="py-2.5 px-3 font-semibold text-title">{{ $h->name }}</td>
                            <td class="py-2.5 px-3 text-right">
                                <form method="POST" action="{{ route('app.hr.leave-config.holidays.destroy', $h) }}" onsubmit="return confirm('{{ __('Delete?') }}')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="size-7 rounded-md border border-border-color inline-flex items-center justify-center text-danger hover:bg-light cursor-pointer" title="{{ __('Delete') }}">
                                        <i class="ph ph-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-8 text-center text-sm text-default">{{ __('No holidays yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white border border-border-color rounded-md">
        <div class="flex items-center justify-between p-4 border-b border-border-color">
            <h2 class="text-base font-bold text-title mb-0">{{ __('Critical Dates') }}</h2>
            <button type="button" data-hs-overlay="#add-critical-modal" class="btn-sm bg-dark text-white border border-dark inline-flex items-center gap-2 hover:bg-primary-hover cursor-pointer">
                <i class="ph ph-plus"></i> {{ __('Add') }}
            </button>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-sm text-default border-b border-border-color">
                        <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Name') }}</th>
                        <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Dates') }}</th>
                        <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Description') }}</th>
                        <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Defined By') }}</th>
                        <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Status') }}</th>
                        <th class="text-right py-2 px-3 font-semibold text-gray-900">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($criticalDates as $c)
                        <tr class="border-b border-border-color">
                            <td class="py-2.5 px-3 font-semibold text-title">{{ $c->name }}</td>
                            <td class="py-2.5 px-3 text-sm">{{ $c->start_date->format('d.m.Y') }} – {{ $c->end_date->format('d.m.Y') }}</td>
                            <td class="py-2.5 px-3 text-xs text-default">{{ Str::limit($c->description, 60) ?? '—' }}</td>
                            <td class="py-2.5 px-3 text-xs text-default">{{ $c->creator?->name ?? '—' }}</td>
                            <td class="py-2.5 px-3">
                                @if ($c->block_leave_requests)
                                    <span class="text-[11px] bg-danger-transparent text-danger px-2 py-0.5 rounded">{{ __('Blocked') }}</span>
                                @else
                                    <span class="text-[11px] bg-warning-transparent text-warning px-2 py-0.5 rounded">{{ __('Warn only') }}</span>
                                @endif
                            </td>
                            <td class="py-2.5 px-3 text-right">
                                <form method="POST" action="{{ route('app.hr.leave-config.critical-dates.destroy', $c) }}" onsubmit="return confirm('{{ __('Delete?') }}')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="size-7 rounded-md border border-border-color inline-flex items-center justify-center text-danger hover:bg-light cursor-pointer" title="{{ __('Delete') }}">
                                        <i class="ph ph-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-8 text-center text-sm text-default">{{ __('No critical dates yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white border border-border-color rounded-md lg:col-span-2 p-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-base font-bold text-title mb-1">{{ __('Hourly Leave (Mazeret İzni)') }}</h2>
            <p class="text-xs text-default mb-0">{{ __('Platform-wide activity, min duration and negative-balance policy are managed on a dedicated screen.') }}</p>
        </div>
        <a href="{{ route('app.hr.leave-config.hourly.index') }}" class="btn-sm bg-white border border-border-color text-gray-900 inline-flex items-center gap-2 hover:bg-light">
            <i class="ph ph-arrow-right"></i> {{ __('Open configuration') }}
        </a>
    </div>

</div>

<div id="add-holiday-modal" class="hs-overlay hidden size-full fixed top-0 start-0 z-[80] overflow-x-hidden overflow-y-auto pointer-events-none">
    <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-lg sm:w-full m-3 sm:mx-auto pointer-events-auto min-h-screen flex items-center justify-center">
        <form method="POST" action="{{ route('app.hr.leave-config.holidays.store') }}" class="flex flex-col bg-white border shadow-sm rounded-md border-border-color w-full">
            @csrf
            <div class="flex justify-between items-center p-4 border-b border-border-color">
                <h2 class="text-base font-bold text-title">{{ __('New Holiday') }}</h2>
                <button type="button" class="size-7 inline-flex justify-center items-center rounded-md border border-border-color hover:bg-light cursor-pointer" data-hs-overlay="#add-holiday-modal"><i class="ph ph-x text-sm"></i></button>
            </div>
            <div class="p-4 grid grid-cols-12 gap-3">
                <div class="col-span-12 sm:col-span-5">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Date') }} <span class="text-danger">*</span></label>
                    <input type="date" name="date" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                </div>
                <div class="col-span-12 sm:col-span-7">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Holiday name') }} <span class="text-danger">*</span></label>
                    <input type="text" name="name" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                </div>
            </div>
            <div class="flex justify-end gap-2 p-4 border-t border-border-color">
                <button type="button" class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light cursor-pointer" data-hs-overlay="#add-holiday-modal">{{ __('Cancel') }}</button>
                <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Save') }}</button>
            </div>
        </form>
    </div>
</div>

<div id="add-critical-modal" class="hs-overlay hidden size-full fixed top-0 start-0 z-[80] overflow-x-hidden overflow-y-auto pointer-events-none">
    <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-lg sm:w-full m-3 sm:mx-auto pointer-events-auto min-h-screen flex items-center justify-center">
        <form method="POST" action="{{ route('app.hr.leave-config.critical-dates.store') }}" class="flex flex-col bg-white border shadow-sm rounded-md border-border-color w-full">
            @csrf
            <div class="flex justify-between items-center p-4 border-b border-border-color">
                <h2 class="text-base font-bold text-title">{{ __('New Critical Date') }}</h2>
                <button type="button" class="size-7 inline-flex justify-center items-center rounded-md border border-border-color hover:bg-light cursor-pointer" data-hs-overlay="#add-critical-modal"><i class="ph ph-x text-sm"></i></button>
            </div>
            <div class="p-4 grid grid-cols-12 gap-3">
                <div class="col-span-12">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Name') }} <span class="text-danger">*</span></label>
                    <input type="text" name="name" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                </div>
                <div class="col-span-12 sm:col-span-6">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Start') }} <span class="text-danger">*</span></label>
                    <input type="date" name="start_date" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                </div>
                <div class="col-span-12 sm:col-span-6">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('End') }} <span class="text-danger">*</span></label>
                    <input type="date" name="end_date" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                </div>
                <div class="col-span-12">
                    <label class="flex items-center gap-2 text-sm text-gray-900">
                        <input type="hidden" name="block_leave_requests" value="0">
                        <input type="checkbox" name="block_leave_requests" value="1">
                        {{ __('Block leave requests in this period') }}
                    </label>
                </div>
            </div>
            <div class="flex justify-end gap-2 p-4 border-t border-border-color">
                <button type="button" class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light cursor-pointer" data-hs-overlay="#add-critical-modal">{{ __('Cancel') }}</button>
                <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Save') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
