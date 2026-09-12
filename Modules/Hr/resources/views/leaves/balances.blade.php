@extends('app.layouts.app')

@section('title', __('Leave Balances'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <div>
        <h1 class="text-gray-900 text-xl font-bold mb-1">{{ __('Leave Balances') }}</h1>
        <p class="text-sm text-default mb-0">{{ __('Year') }}: <strong>{{ $year }}</strong></p>
    </div>
    <form method="GET" class="flex items-center gap-2">
        <input type="number" name="year" value="{{ $year }}" min="2020" max="2100" class="form-control w-32">
        <button type="submit" class="btn-sm bg-white border border-border-color text-gray-900">{{ __('Filter') }}</button>
    </form>
</div>

@if (session('status'))
    <div class="bg-success-transparent text-success border border-success rounded-md px-4 py-3 text-sm mb-4">{{ session('status') }}</div>
@endif
@if ($errors->any())
    <div class="bg-danger-transparent text-danger border border-danger rounded-md px-4 py-3 text-sm mb-4">
        @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
    </div>
@endif

<div class="bg-white border border-border-color rounded-md overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-border-color text-default">
                <th class="text-left py-2 px-3">{{ __('Employee') }}</th>
                <th class="text-right py-2 px-3">{{ __('Carried') }}</th>
                <th class="text-right py-2 px-3">{{ __('Annual Entitlement') }}</th>
                <th class="text-right py-2 px-3">{{ __('Manual Adj.') }}</th>
                <th class="text-right py-2 px-3">{{ __('Current') }}</th>
                <th class="text-left py-2 px-3">{{ __('Actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($employees as $e)
                @php $b = $balances[$e->id] ?? null; @endphp
                <tr class="border-b border-border-color">
                    <td class="py-2.5 px-3 font-semibold">{{ $e->full_name }}</td>
                    <td class="py-2.5 px-3 text-right">{{ $b?->carried_from_previous ?? '—' }}</td>
                    <td class="py-2.5 px-3 text-right">{{ $b?->current_year_entitlement ?? '—' }}</td>
                    <td class="py-2.5 px-3 text-right">{{ $b?->manual_adjustment ?? '—' }}</td>
                    <td class="py-2.5 px-3 text-right font-semibold">{{ $e->annual_leave_balance }}</td>
                    <td class="py-2.5 px-3">
                        <button type="button" data-hs-overlay="#adj-{{ $e->id }}" class="text-xs text-primary hover:underline">{{ __('Adjust') }}</button>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

@foreach ($employees as $e)
    @php $b = $balances[$e->id] ?? null; @endphp
    <div id="adj-{{ $e->id }}" class="hs-overlay hidden fixed inset-0 z-50 overflow-x-hidden overflow-y-auto pointer-events-none">
        <div class="hs-overlay-open:opacity-100 opacity-0 transition-all sm:max-w-lg sm:w-full m-3 sm:mx-auto min-h-[calc(100%-56px)] flex items-center">
            <div class="pointer-events-auto bg-white border border-border-color rounded-md w-full">
                <form method="POST" action="{{ route('app.hr.leave-balances.upsert') }}">
                    @csrf
                    <input type="hidden" name="employee_id" value="{{ $e->id }}">
                    <input type="hidden" name="year" value="{{ $year }}">
                    <div class="p-4 border-b border-border-color">
                        <h3 class="text-base font-bold text-title mb-0">{{ __('Adjust Balance') }} — {{ $e->full_name }}</h3>
                    </div>
                    <div class="p-4 space-y-3">
                        <div>
                            <label class="block text-sm text-gray-900 mb-1">{{ __('Carried from previous') }}</label>
                            <input type="number" step="0.5" name="carried_from_previous" value="{{ $b?->carried_from_previous ?? 0 }}" class="form-control">
                        </div>
                        <div>
                            <label class="block text-sm text-gray-900 mb-1">{{ __('Annual Entitlement') }} <span class="text-danger">*</span></label>
                            <input type="number" step="0.5" name="current_year_entitlement" value="{{ $b?->current_year_entitlement ?? 14 }}" class="form-control" required>
                        </div>
                        <div>
                            <label class="block text-sm text-gray-900 mb-1">{{ __('Manual Adjustment') }}</label>
                            <input type="number" step="0.5" name="manual_adjustment" value="{{ $b?->manual_adjustment ?? 0 }}" class="form-control">
                        </div>
                        <div>
                            <label class="block text-sm text-gray-900 mb-1">{{ __('Adjustment Reason') }}</label>
                            <textarea name="adjustment_reason" rows="2" class="form-control">{{ $b?->adjustment_reason }}</textarea>
                        </div>
                    </div>
                    <div class="flex items-center justify-end gap-2 p-4 border-t border-border-color">
                        <button type="button" data-hs-overlay="#adj-{{ $e->id }}" class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light">{{ __('Cancel') }}</button>
                        <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover">{{ __('Save') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endforeach
@endsection
