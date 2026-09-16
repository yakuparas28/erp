@extends('app.layouts.app')

@section('title', __('Salary Advances'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <div>
        <h1 class="text-gray-900 text-xl max-lg:text-lg font-bold mb-0">{{ __('Salary Advances') }}</h1>
        <p class="text-[12px] text-default mb-0 mt-1">
            {{ __('Grant an advance from a cash/bank account. Outstanding advances are automatically deducted from the next payroll payment (FIFO).') }}
        </p>
    </div>
    <button type="button" data-adv-open-add class="btn-sm bg-primary text-white border border-primary hover:bg-primary/90 cursor-pointer inline-flex items-center gap-2">
        <i class="ph ph-plus"></i> {{ __('Grant Advance') }}
    </button>
</div>

@if (session('status'))
    <div class="bg-success-transparent text-success border border-success rounded-md px-4 py-3 text-sm mb-4">{{ session('status') }}</div>
@endif
@error('advance')
    <div class="bg-danger-transparent text-danger border border-danger rounded-md px-4 py-3 text-sm mb-4">{{ $message }}</div>
@enderror

<div class="bg-white border border-border-color rounded-md p-4">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-default text-[11px] uppercase font-medium">
                    <th class="text-left py-2 border-b border-border-color">{{ __('Employee') }}</th>
                    <th class="text-left py-2 border-b border-border-color">{{ __('Granted') }}</th>
                    <th class="text-left py-2 border-b border-border-color">{{ __('Paid from') }}</th>
                    <th class="text-right py-2 border-b border-border-color">{{ __('Amount') }}</th>
                    <th class="text-left py-2 border-b border-border-color">{{ __('Status') }}</th>
                    <th class="text-left py-2 border-b border-border-color">{{ __('Deducted') }}</th>
                    <th class="py-2 border-b border-border-color"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($advances as $adv)
                    @php
                        $badge = match ($adv->status) {
                            'outstanding' => 'bg-warning-transparent text-warning',
                            'deducted' => 'bg-success-transparent text-success',
                            'cancelled' => 'bg-danger-transparent text-danger',
                            default => 'bg-light text-default',
                        };
                    @endphp
                    <tr class="border-b border-border-color">
                        <td class="py-2 text-gray-900">
                            {{ $adv->employee->first_name }} {{ $adv->employee->last_name }}
                            <div class="text-[11px] text-default">{{ optional($adv->employee->department)->name ?? '—' }}</div>
                        </td>
                        <td class="py-2 text-default text-[12px]">{{ $adv->granted_at->format('d.m.Y') }}</td>
                        <td class="py-2 text-default text-[12px]">{{ optional($adv->paidFromJournal)->name ?? '—' }}</td>
                        <td class="py-2 text-right font-semibold text-gray-900">{{ number_format((float) $adv->amount, 2, ',', '.') }}</td>
                        <td class="py-2">
                            <span class="text-[11px] px-2 py-0.5 rounded {{ $badge }}">
                                {{ __(ucfirst($adv->status)) }}
                            </span>
                        </td>
                        <td class="py-2 text-default text-[12px]">
                            @if ($adv->deducted_at)
                                {{ $adv->deducted_at->format('d.m.Y') }}
                                @if ($adv->deductedInPayslip?->period)
                                    <div class="text-[11px]">{{ $adv->deductedInPayslip->period->label() }}</div>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td class="py-2 text-right text-default text-[12px]">
                            @if ($adv->notes)
                                <span title="{{ $adv->notes }}">📝</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-6 text-center text-default">{{ __('No advances yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $advances->links() }}</div>
</div>

<div id="adv-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4">
    <div class="bg-white rounded-md shadow-xl w-full" style="max-width: min(500px, calc(100vw - 32px));">
        <form method="POST" action="{{ route('app.hr.salary-advances.store') }}">
            @csrf
            <div class="flex items-center justify-between border-b border-border-color px-4 py-3">
                <h2 class="text-base font-bold text-title inline-flex items-center gap-2"><i class="ph ph-hand-coins"></i> {{ __('Grant Salary Advance') }}</h2>
                <button type="button" data-adv-close class="text-default hover:text-gray-900 cursor-pointer"><i class="ph ph-x text-lg"></i></button>
            </div>
            <div class="p-4 space-y-3">
                <div>
                    <label class="text-[12px] text-default block mb-1">{{ __('Employee') }} *</label>
                    <select name="employee_id" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                        @foreach ($employees as $emp)
                            <option value="{{ $emp->id }}">{{ $emp->first_name }} {{ $emp->last_name }} ({{ __('gross') }}: {{ number_format((float) $emp->gross_salary, 2, ',', '.') }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-[12px] text-default block mb-1">{{ __('Amount') }} *</label>
                        <input type="number" step="0.01" min="0.01" name="amount" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0 text-right">
                    </div>
                    <div>
                        <label class="text-[12px] text-default block mb-1">{{ __('Paid from') }} *</label>
                        <select name="journal_id" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                            @foreach ($journals as $j)
                                <option value="{{ $j->id }}">{{ $j->name }} ({{ __(ucfirst($j->type)) }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <label class="text-[12px] text-default block mb-1">{{ __('Notes') }}</label>
                    <textarea name="notes" rows="2" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0"></textarea>
                </div>
            </div>
            <div class="border-t border-border-color px-4 py-3 flex items-center justify-end gap-2">
                <button type="button" data-adv-close class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light cursor-pointer">{{ __('Cancel') }}</button>
                <button type="submit" class="btn-sm bg-primary text-white border border-primary hover:bg-primary/90 cursor-pointer inline-flex items-center gap-2"><i class="ph ph-check"></i> {{ __('Grant') }}</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const modal = document.getElementById('adv-modal');
    const open = () => { modal.classList.remove('hidden'); modal.classList.add('flex'); };
    const close = () => { modal.classList.remove('flex'); modal.classList.add('hidden'); };
    document.querySelectorAll('[data-adv-open-add]').forEach((b) => b.addEventListener('click', open));
    document.querySelectorAll('[data-adv-close]').forEach((b) => b.addEventListener('click', close));
    modal.addEventListener('click', (e) => { if (e.target === modal) close(); });
})();
</script>
@endsection
