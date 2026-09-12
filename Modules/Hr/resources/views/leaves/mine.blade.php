@extends('app.layouts.app')

@section('title', __('My Leave Requests'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <h1 class="text-gray-900 text-xl font-bold mb-0">{{ __('My Leave Requests') }}</h1>
    <button type="button" data-hs-overlay="#add-leave-modal" class="btn-sm bg-dark text-white border border-dark inline-flex items-center gap-2 hover:bg-primary-hover">
        <i class="ph ph-plus"></i> {{ __('Apply Leave') }}
    </button>
</div>

@if (session('status'))
    <div class="bg-success-transparent text-success border border-success rounded-md px-4 py-3 text-sm mb-4">{{ session('status') }}</div>
@endif
@if ($errors->any())
    <div class="bg-danger-transparent text-danger border border-danger rounded-md px-4 py-3 text-sm mb-4">
        @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
    </div>
@endif

<div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
    <div class="bg-white border border-border-color rounded-md p-4">
        <div class="text-xs text-default mb-1">{{ __('Current annual leave balance') }}</div>
        <div class="text-2xl font-bold text-title">{{ $employee->annual_leave_balance }} <span class="text-sm text-default font-normal">{{ __('days') }}</span></div>
    </div>
    <div class="bg-white border border-border-color rounded-md p-4">
        <div class="text-xs text-default mb-1">{{ __('Pending') }}</div>
        <div class="text-2xl font-bold text-warning">{{ $requests->filter(fn ($r) => ($r->approval?->status ?? 'pending') === 'pending')->count() }}</div>
    </div>
    <div class="bg-white border border-border-color rounded-md p-4">
        <div class="text-xs text-default mb-1">{{ __('Approved') }}</div>
        <div class="text-2xl font-bold text-success">{{ $requests->filter(fn ($r) => $r->approval?->status === 'approved')->count() }}</div>
    </div>
</div>

<div class="bg-white border border-border-color rounded-md p-4">
    <div class="overflow-x-auto -mx-4">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-y border-border-color bg-light">
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('ID') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Type') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('From') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('To') }}</th>
                    <th class="text-right py-2 px-3 font-semibold text-gray-900">{{ __('Days') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Status') }}</th>
                    <th class="text-center py-2 px-3 font-semibold text-gray-900">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($requests as $r)
                    <tr class="border-b border-border-color hover:bg-light/50">
                        <td class="py-3 px-3 font-mono text-xs text-primary">LV-{{ str_pad((string) $r->id, 5, '0', STR_PAD_LEFT) }}</td>
                        <td class="py-3 px-3 font-semibold text-title">{{ $r->leaveType->name }}</td>
                        <td class="py-3 px-3">{{ $r->start_date->format('d.m.Y') }}</td>
                        <td class="py-3 px-3">{{ $r->end_date->format('d.m.Y') }}</td>
                        <td class="py-3 px-3 text-right font-semibold">{{ $r->total_days }}</td>
                        <td class="py-3 px-3">
                            @php $s = $r->approval?->status ?? 'pending'; @endphp
                            @if ($s === 'approved')
                                <span class="text-[11px] bg-success-transparent text-success px-2 py-0.5 rounded">{{ __('Approved') }}</span>
                            @elseif ($s === 'rejected')
                                <span class="text-[11px] bg-danger-transparent text-danger px-2 py-0.5 rounded">{{ __('Rejected') }}</span>
                            @elseif ($s === 'cancelled')
                                <span class="text-[11px] bg-light text-default px-2 py-0.5 rounded border border-border-color">{{ __('Cancelled') }}</span>
                            @else
                                <span class="text-[11px] bg-warning-transparent text-warning px-2 py-0.5 rounded">{{ __('Pending') }}</span>
                            @endif
                        </td>
                        <td class="py-3 px-3 text-center">
                            @if ($s === 'pending')
                                <form method="POST" action="{{ route('app.hr.leaves.cancel', $r) }}" onsubmit="return confirm('{{ __('Cancel this request?') }}')" class="inline">
                                    @csrf
                                    <button type="submit" class="size-7 rounded-md border border-border-color inline-flex items-center justify-center text-danger hover:bg-light" title="{{ __('Cancel') }}">
                                        <i class="ph ph-x"></i>
                                    </button>
                                </form>
                            @else
                                <span class="text-default">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-8 text-center text-default">{{ __('No leave requests yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div id="add-leave-modal" class="hs-overlay hidden fixed inset-0 z-50 overflow-x-hidden overflow-y-auto pointer-events-none">
    <div class="hs-overlay-open:opacity-100 opacity-0 transition-all sm:max-w-lg sm:w-full m-3 sm:mx-auto min-h-[calc(100%-56px)] flex items-center">
        <div class="pointer-events-auto bg-white border border-border-color rounded-md w-full shadow">
            <form method="POST" action="{{ route('app.hr.leaves.store') }}">
                @csrf
                <div class="p-4 border-b border-border-color flex items-center justify-between">
                    <h3 class="text-base font-bold text-title mb-0">{{ __('New Leave Request') }}</h3>
                    <button type="button" data-hs-overlay="#add-leave-modal" class="size-7 rounded-md border border-border-color flex items-center justify-center hover:bg-light">
                        <i class="ph ph-x"></i>
                    </button>
                </div>
                <div class="p-4 space-y-3">
                    <div>
                        <label class="block text-sm font-semibold text-gray-900 mb-1">{{ __('Type') }} <span class="text-danger">*</span></label>
                        <select name="leave_type_id" class="form-control" required>
                            <option value="">{{ __('Select type') }}</option>
                            @foreach ($leaveTypes as $t)
                                <option value="{{ $t->id }}">{{ $t->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-semibold text-gray-900 mb-1">{{ __('From') }} <span class="text-danger">*</span></label>
                            <input type="date" name="start_date" class="form-control" required>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-900 mb-1">{{ __('To') }} <span class="text-danger">*</span></label>
                            <input type="date" name="end_date" class="form-control" required>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-900 mb-1">{{ __('Half day') }}</label>
                        <select name="half_day_type" class="form-control">
                            <option value="">—</option>
                            <option value="morning">{{ __('Morning') }}</option>
                            <option value="afternoon">{{ __('Afternoon') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-900 mb-1">{{ __('Reason') }}</label>
                        <textarea name="reason" rows="3" class="form-control" placeholder="{{ __('Optional description') }}"></textarea>
                    </div>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="hidden" name="travel_allowance_requested" value="0">
                        <input type="checkbox" name="travel_allowance_requested" value="1"> {{ __('Travel allowance requested') }}
                    </label>
                </div>
                <div class="flex items-center justify-end gap-2 p-4 border-t border-border-color">
                    <button type="button" data-hs-overlay="#add-leave-modal" class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover">{{ __('Submit') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
