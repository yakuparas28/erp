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

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

    <div class="bg-white border border-border-color rounded-md p-4">
        <h2 class="text-base font-bold text-title mb-3">{{ __('Holidays') }}</h2>
        <form method="POST" action="{{ route('app.hr.leave-config.holidays.store') }}" class="grid grid-cols-3 gap-2 mb-3">
            @csrf
            <input type="date" name="date" class="form-control" required>
            <input type="text" name="name" placeholder="{{ __('Holiday name') }}" class="form-control" required>
            <button type="submit" class="btn-sm bg-dark text-white">{{ __('Add') }}</button>
        </form>
        <table class="w-full text-sm">
            <tbody>
                @foreach ($holidays as $h)
                    <tr class="border-t border-border-color">
                        <td class="py-2">{{ $h->date->format('d.m.Y') }}</td>
                        <td class="py-2">{{ $h->name }}</td>
                        <td class="py-2 text-right">
                            <form method="POST" action="{{ route('app.hr.leave-config.holidays.destroy', $h) }}" onsubmit="return confirm('{{ __('Delete?') }}')">
                                @csrf @method('DELETE')
                                <button class="text-xs text-danger">{{ __('Delete') }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="bg-white border border-border-color rounded-md p-4">
        <h2 class="text-base font-bold text-title mb-3">{{ __('Critical Dates') }}</h2>
        <form method="POST" action="{{ route('app.hr.leave-config.critical-dates.store') }}" class="space-y-2 mb-3">
            @csrf
            <input type="text" name="name" placeholder="{{ __('Name') }}" class="form-control" required>
            <div class="grid grid-cols-2 gap-2">
                <input type="date" name="start_date" class="form-control" required>
                <input type="date" name="end_date" class="form-control" required>
            </div>
            <label class="flex items-center gap-2 text-sm">
                <input type="hidden" name="block_leave_requests" value="0">
                <input type="checkbox" name="block_leave_requests" value="1">
                {{ __('Block leave requests in this period') }}
            </label>
            <button type="submit" class="btn-sm bg-dark text-white">{{ __('Add') }}</button>
        </form>
        <table class="w-full text-sm">
            <tbody>
                @foreach ($criticalDates as $c)
                    <tr class="border-t border-border-color">
                        <td class="py-2 font-semibold">{{ $c->name }}</td>
                        <td class="py-2">{{ $c->start_date->format('d.m.Y') }} – {{ $c->end_date->format('d.m.Y') }}</td>
                        <td class="py-2">{{ $c->block_leave_requests ? __('Blocked') : __('Warn only') }}</td>
                        <td class="py-2 text-right">
                            <form method="POST" action="{{ route('app.hr.leave-config.critical-dates.destroy', $c) }}" onsubmit="return confirm('{{ __('Delete?') }}')">
                                @csrf @method('DELETE')
                                <button class="text-xs text-danger">{{ __('Delete') }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="bg-white border border-border-color rounded-md p-4 lg:col-span-2">
        <h2 class="text-base font-bold text-title mb-3">{{ __('Hourly Leave Settings') }}</h2>
        <form method="POST" action="{{ route('app.hr.leave-config.hour-configs.store') }}" class="grid grid-cols-4 gap-2 mb-3">
            @csrf
            <select name="department_id" class="form-control">
                <option value="">{{ __('Default (all)') }}</option>
                @foreach ($departments as $d)
                    <option value="{{ $d->id }}">{{ $d->name }}</option>
                @endforeach
            </select>
            <input type="number" step="0.5" name="daily_work_hours" placeholder="{{ __('Daily hours') }}" class="form-control" required>
            <input type="number" step="0.5" name="monthly_leave_hours" placeholder="{{ __('Monthly leave hours') }}" class="form-control" required>
            <button type="submit" class="btn-sm bg-dark text-white">{{ __('Save') }}</button>
        </form>
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-border-color text-default">
                    <th class="text-left py-2">{{ __('Department') }}</th>
                    <th class="text-right py-2">{{ __('Daily') }}</th>
                    <th class="text-right py-2">{{ __('Monthly limit') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($hourConfigs as $c)
                    <tr class="border-t border-border-color">
                        <td class="py-2">{{ $c->department?->name ?? __('Default (all)') }}</td>
                        <td class="py-2 text-right">{{ $c->daily_work_hours }}</td>
                        <td class="py-2 text-right">{{ $c->monthly_leave_hours }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
