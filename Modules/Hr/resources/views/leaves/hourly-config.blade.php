@extends('app.layouts.app')

@section('title', __('Hourly Leave Configuration'))

@section('content')
<div class="mb-3 lg:mb-6">
    <h1 class="text-gray-900 text-xl font-bold mb-1">{{ __('Hourly Leave Configuration') }}</h1>
    <p class="text-sm text-default mb-0">{{ __('Spec v4.1 §9.5 / BR-IT-13 — activity flag, minimum duration and daily working hours (used for fractional-day balance).') }}</p>
</div>

@if (session('status'))
    <div class="bg-success-transparent text-success border border-success rounded-md px-4 py-3 text-sm mb-4">{{ session('status') }}</div>
@endif
@if ($errors->any())
    <div class="bg-danger-transparent text-danger border border-danger rounded-md px-4 py-3 text-sm mb-4">
        @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
    </div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-3 items-start">

    <div class="lg:col-span-2 bg-white border border-border-color rounded-md">
        <div class="p-4 border-b border-border-color">
            <h2 class="text-base font-bold text-title mb-0">{{ __('Platform-wide Settings') }}</h2>
        </div>
        <form method="POST" action="{{ route('app.hr.leave-config.hourly.platform') }}" class="p-4 space-y-5">
            @csrf
            @method('PATCH')

            <label class="flex items-start gap-3 cursor-pointer">
                <input type="hidden" name="is_active" value="0">
                <span class="relative inline-flex items-center mt-0.5">
                    <input type="checkbox" name="is_active" value="1" @checked($platform->is_active) class="peer sr-only">
                    <span class="w-10 h-6 rounded-full border border-border-color bg-light peer-checked:bg-primary peer-checked:border-primary transition-colors"></span>
                    <span class="absolute left-0.5 top-0.5 size-5 rounded-full bg-white shadow transition-transform peer-checked:translate-x-4"></span>
                </span>
                <span>
                    <span class="block text-sm font-semibold text-gray-900">{{ __('Hourly leave enabled organization-wide') }}</span>
                    <span class="block text-xs text-default">{{ __('When disabled, employees cannot submit hourly leave requests.') }}</span>
                </span>
            </label>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Min. request duration (hours)') }} <span class="text-danger">*</span></label>
                    <select name="min_hours" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                        @foreach (['0.5', '1', '2', '4'] as $opt)
                            <option value="{{ $opt }}" @selected(number_format((float) $platform->min_hours, 2, '.', '') === number_format((float) $opt, 2, '.', ''))>{{ $opt }} {{ __('hour') }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-default mt-1 mb-0">{{ __('BR-IT-13 — default minimum request duration.') }}</p>
                </div>
                <div>
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Daily working hours') }} <span class="text-danger">*</span></label>
                    <input type="number" step="0.5" min="1" max="24" name="daily_work_hours" required value="{{ $platform->daily_work_hours }}" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                    <p class="text-xs text-default mt-1 mb-0">{{ __('Fractional balance calc (e.g. 2h / 8h = 0.25 day).') }}</p>
                </div>
            </div>

            <div class="border-t border-border-color pt-4">
                <h3 class="text-sm font-semibold text-title mb-1">{{ __('Negative balance policy (Spec §9.5.5 / BR-IT-15)') }}</h3>
                <p class="text-xs text-default mb-3">{{ __('Behavior when an employee\'s leave balance would drop below zero.') }}</p>
                <label class="flex items-start gap-3 p-3 border border-border-color rounded-md cursor-pointer hover:bg-light mb-2 {{ $platform->negative_balance_policy === 'strict' ? 'border-primary bg-primary-transparent' : '' }}">
                    <input type="radio" name="negative_balance_policy" value="strict" @checked($platform->negative_balance_policy === 'strict') class="mt-1">
                    <span>
                        <span class="block text-sm font-semibold text-gray-900">{{ __('Do NOT allow negative balance (strict)') }}</span>
                        <span class="block text-xs text-default">{{ __('New annual leave requests are blocked once the balance runs out. Suggests unpaid leave instead.') }}</span>
                    </span>
                </label>
                <label class="flex items-start gap-3 p-3 border border-border-color rounded-md cursor-pointer hover:bg-light {{ $platform->negative_balance_policy === 'lenient' ? 'border-primary bg-primary-transparent' : '' }}">
                    <input type="radio" name="negative_balance_policy" value="lenient" @checked($platform->negative_balance_policy === 'lenient') class="mt-1">
                    <span>
                        <span class="block text-sm font-semibold text-gray-900">{{ __('Allow negative balance (lenient — offset)') }}</span>
                        <span class="block text-xs text-default">{{ __('Employees keep requesting; balance goes negative and is offset against next year\'s accrual.') }}</span>
                    </span>
                </label>
            </div>

            <div class="flex justify-end pt-2 border-t border-border-color">
                <button type="submit" class="btn-sm bg-dark text-white border border-dark inline-flex items-center gap-2 hover:bg-primary-hover cursor-pointer">
                    <i class="ph ph-floppy-disk"></i> {{ __('Save') }}
                </button>
            </div>
        </form>
    </div>

    <div class="bg-white border border-border-color rounded-md">
        <div class="p-4 border-b border-border-color">
            <h2 class="text-base font-bold text-title mb-0">{{ __('Department Override') }}</h2>
            <p class="text-xs text-default mt-1 mb-0">{{ __('Spec §9.5.1 / §13.6 — listed departments use a different min. duration.') }}</p>
        </div>
        <form method="POST" action="{{ route('app.hr.leave-config.hourly.override.store') }}" class="p-4 grid grid-cols-12 gap-2 border-b border-border-color">
            @csrf
            <div class="col-span-5">
                <label class="text-xs font-semibold text-gray-900 mb-1 block">{{ __('Department') }}</label>
                <select name="department_id" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                    <option value="">{{ __('Select') }}</option>
                    @foreach ($departments as $d)
                        @if (! $overrides->pluck('department_id')->contains($d->id))
                            <option value="{{ $d->id }}">{{ $d->name }}</option>
                        @endif
                    @endforeach
                </select>
            </div>
            <div class="col-span-5">
                <label class="text-xs font-semibold text-gray-900 mb-1 block">{{ __('Min. request') }}</label>
                <select name="min_hours" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                    @foreach (['0.5', '1', '2', '4'] as $opt)
                        <option value="{{ $opt }}">{{ $opt }} {{ __('hour') }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-span-2 flex items-end">
                <button type="submit" class="btn-sm bg-primary text-white border border-primary inline-flex items-center justify-center gap-1 w-full hover:bg-primary-hover cursor-pointer" title="{{ __('Add') }}">
                    <i class="ph ph-plus"></i>
                </button>
            </div>
        </form>

        @if ($overrides->isEmpty())
            <p class="text-xs text-default text-center py-6">{{ __('No department override yet. Platform-wide settings apply.') }}</p>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-sm text-default border-b border-border-color">
                        <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Department') }}</th>
                        <th class="text-right py-2 px-3 font-semibold text-gray-900">{{ __('Min. hours') }}</th>
                        <th class="text-right py-2 px-3 font-semibold text-gray-900">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($overrides as $o)
                        <tr class="border-b border-border-color">
                            <td class="py-2.5 px-3 font-semibold text-title">{{ $o->department->name }}</td>
                            <td class="py-2.5 px-3 text-right">{{ $o->min_hours }} {{ __('hour') }}</td>
                            <td class="py-2.5 px-3 text-right">
                                <form method="POST" action="{{ route('app.hr.leave-config.hourly.override.destroy', $o) }}" onsubmit="return confirm('{{ __('Delete?') }}')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="size-7 rounded-md border border-border-color inline-flex items-center justify-center text-danger hover:bg-light cursor-pointer" title="{{ __('Delete') }}">
                                        <i class="ph ph-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

</div>
@endsection
