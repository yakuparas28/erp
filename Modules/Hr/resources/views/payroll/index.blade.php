@extends('app.layouts.app')

@section('title', __('Payroll'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <div>
        <h1 class="text-gray-900 text-xl max-lg:text-lg font-bold mb-0">{{ __('Payroll') }}</h1>
        <p class="text-[12px] text-default mb-0 mt-1">
            {{ __('Monthly salary accrual. :n employees currently have a gross salary set.', ['n' => $employeesWithSalary]) }}
            @if ($employeesWithSalary === 0)
                <a href="{{ route('app.hr.employees.index') }}" class="text-primary hover:underline">{{ __('Set salaries on employees →') }}</a>
            @endif
        </p>
    </div>
    <button type="button" data-pr-open-add class="btn-sm bg-primary text-white border border-primary hover:bg-primary/90 cursor-pointer inline-flex items-center gap-2">
        <i class="ph ph-plus"></i> {{ __('Open Period') }}
    </button>
</div>

@if (session('status'))
    <div class="bg-success-transparent text-success border border-success rounded-md px-4 py-3 text-sm mb-4">{{ session('status') }}</div>
@endif

<div class="bg-white border border-border-color rounded-md p-4">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-default text-[11px] uppercase font-medium">
                    <th class="text-left py-2 border-b border-border-color">{{ __('Period') }}</th>
                    <th class="text-center py-2 border-b border-border-color">{{ __('Payslips') }}</th>
                    <th class="text-right py-2 border-b border-border-color">{{ __('Total Gross') }}</th>
                    <th class="text-right py-2 border-b border-border-color">{{ __('Total Net') }}</th>
                    <th class="text-right py-2 border-b border-border-color">{{ __('Employer Cost') }}</th>
                    <th class="text-left py-2 border-b border-border-color">{{ __('Status') }}</th>
                    <th class="py-2 border-b border-border-color"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($periods as $period)
                    @php
                        $badge = match ($period->status) {
                            'draft' => 'bg-light text-default',
                            'calculated' => 'bg-warning-transparent text-warning',
                            'posted' => 'bg-success-transparent text-success',
                            'cancelled' => 'bg-danger-transparent text-danger',
                            default => 'bg-light text-default',
                        };
                    @endphp
                    <tr class="border-b border-border-color">
                        <td class="py-2 font-medium text-gray-900 font-mono">{{ $period->label() }}</td>
                        <td class="py-2 text-center text-default">{{ $period->payslips_count }}</td>
                        <td class="py-2 text-right text-default">{{ number_format((float) $period->total_gross, 2, ',', '.') }}</td>
                        <td class="py-2 text-right font-semibold text-success">{{ number_format((float) $period->total_net, 2, ',', '.') }}</td>
                        <td class="py-2 text-right text-danger">{{ number_format((float) $period->total_employer_cost, 2, ',', '.') }}</td>
                        <td class="py-2">
                            <span class="text-[11px] px-2 py-0.5 rounded {{ $badge }}">
                                {{ __(ucfirst($period->status)) }}
                            </span>
                        </td>
                        <td class="py-2 text-right">
                            <a href="{{ route('app.hr.payroll.show', $period) }}" class="text-primary text-[12px] hover:underline">{{ __('Open') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-6 text-center text-default">{{ __('No payroll periods yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $periods->links() }}</div>
</div>

<div id="pr-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4">
    <div class="bg-white rounded-md shadow-xl w-full" style="max-width: min(420px, calc(100vw - 32px));">
        <form method="POST" action="{{ route('app.hr.payroll.store') }}">
            @csrf
            <div class="flex items-center justify-between border-b border-border-color px-4 py-3">
                <h2 class="text-base font-bold text-title inline-flex items-center gap-2"><i class="ph ph-calendar-plus"></i> {{ __('Open Payroll Period') }}</h2>
                <button type="button" data-pr-close class="text-default hover:text-gray-900 cursor-pointer"><i class="ph ph-x text-lg"></i></button>
            </div>
            <div class="p-4 grid grid-cols-2 gap-3">
                <div>
                    <label class="text-[12px] text-default block mb-1">{{ __('Year') }} *</label>
                    <input type="number" min="2020" max="2100" name="year" value="{{ now()->year }}" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                </div>
                <div>
                    <label class="text-[12px] text-default block mb-1">{{ __('Month') }} *</label>
                    <select name="month" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                        @for ($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}" @selected($m === (int) now()->month)>{{ str_pad((string) $m, 2, '0', STR_PAD_LEFT) }}</option>
                        @endfor
                    </select>
                </div>
            </div>
            <div class="border-t border-border-color px-4 py-3 flex items-center justify-end gap-2">
                <button type="button" data-pr-close class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light cursor-pointer">{{ __('Cancel') }}</button>
                <button type="submit" class="btn-sm bg-primary text-white border border-primary hover:bg-primary/90 cursor-pointer inline-flex items-center gap-2"><i class="ph ph-check"></i> {{ __('Open') }}</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const modal = document.getElementById('pr-modal');
    const open = () => { modal.classList.remove('hidden'); modal.classList.add('flex'); };
    const close = () => { modal.classList.remove('flex'); modal.classList.add('hidden'); };
    document.querySelectorAll('[data-pr-open-add]').forEach((b) => b.addEventListener('click', open));
    document.querySelectorAll('[data-pr-close]').forEach((b) => b.addEventListener('click', close));
    modal.addEventListener('click', (e) => { if (e.target === modal) close(); });
})();
</script>
@endsection
