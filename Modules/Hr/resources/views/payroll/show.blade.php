@extends('app.layouts.app')

@section('title', __('Payroll').' — '.$period->label())

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <div>
        <h1 class="text-gray-900 text-xl font-bold mb-1">{{ __('Payroll') }} — {{ $period->label() }}</h1>
        @php
            $badge = match ($period->status) {
                'draft' => 'bg-light text-default',
                'calculated' => 'bg-warning-transparent text-warning',
                'posted' => 'bg-success-transparent text-success',
                default => 'bg-light text-default',
            };
        @endphp
        <span class="text-[11px] px-2 py-0.5 rounded {{ $badge }}">{{ __(ucfirst($period->status)) }}</span>
        @if ($period->posted_at)
            <span class="text-[12px] text-default ms-2">{{ __('Posted') }} {{ $period->posted_at->format('d.m.Y') }}</span>
        @endif
    </div>
    <a href="{{ route('app.hr.payroll.index') }}" class="btn-sm bg-white border border-border-color text-gray-900 inline-flex items-center gap-2">
        <i class="ph ph-arrow-left"></i> {{ __('Back') }}
    </a>
</div>

@if (session('status'))
    <div class="bg-success-transparent text-success border border-success rounded-md px-4 py-3 text-sm mb-4">{{ session('status') }}</div>
@endif
@error('period')
    <div class="bg-danger-transparent text-danger border border-danger rounded-md px-4 py-3 text-sm mb-4">{{ $message }}</div>
@enderror
@error('pay')
    <div class="bg-danger-transparent text-danger border border-danger rounded-md px-4 py-3 text-sm mb-4">{{ $message }}</div>
@enderror

<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
    <div class="bg-white border border-border-color rounded-md p-3">
        <div class="text-[11px] uppercase text-default">{{ __('Total Gross') }}</div>
        <div class="text-lg font-bold text-gray-900">{{ number_format((float) $period->total_gross, 2, ',', '.') }}</div>
    </div>
    <div class="bg-white border border-border-color rounded-md p-3">
        <div class="text-[11px] uppercase text-default">{{ __('Total Deductions') }}</div>
        <div class="text-lg font-bold text-danger">{{ number_format((float) $period->total_deductions, 2, ',', '.') }}</div>
    </div>
    <div class="bg-white border border-border-color rounded-md p-3">
        <div class="text-[11px] uppercase text-default">{{ __('Total Net') }}</div>
        <div class="text-lg font-bold text-success">{{ number_format((float) $period->total_net, 2, ',', '.') }}</div>
    </div>
    <div class="bg-white border border-border-color rounded-md p-3">
        <div class="text-[11px] uppercase text-default">{{ __('Employer Cost') }}</div>
        <div class="text-lg font-bold text-gray-900">{{ number_format((float) $period->total_employer_cost, 2, ',', '.') }}</div>
    </div>
</div>

<div class="bg-white border border-border-color rounded-md p-4 mb-4 flex flex-wrap items-center gap-2">
    @if ($period->status !== 'posted')
        <form method="POST" action="{{ route('app.hr.payroll.generate', $period) }}" class="inline">
            @csrf
            <button type="submit" class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light cursor-pointer inline-flex items-center gap-2">
                <i class="ph ph-calculator"></i> {{ $period->payslips->isEmpty() ? __('Calculate Payslips') : __('Recalculate') }}
            </button>
        </form>
        @if ($period->status === 'calculated' && $period->payslips->isNotEmpty())
            <form method="POST" action="{{ route('app.hr.payroll.post', $period) }}" class="inline" onsubmit="return confirm('{{ __('Post this payroll to the general journal? This creates a permanent JE.') }}')">
                @csrf
                <button type="submit" class="btn-sm bg-primary text-white border border-primary hover:bg-primary/90 cursor-pointer inline-flex items-center gap-2">
                    <i class="ph ph-book-open-text"></i> {{ __('Post to Journal') }}
                </button>
            </form>
        @endif
    @else
        <span class="text-[12px] text-success">✓ {{ __('This period has been posted to the general journal.') }}</span>

        @php $unpaidCount = $period->payslips->where('status', 'calculated')->count(); @endphp
        @if ($unpaidCount > 0)
            <div class="border-l border-border-color pl-3 ml-2 flex flex-wrap items-center gap-2">
                <a href="{{ route('app.hr.payroll.bank-transfer-file', $period) }}" class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light cursor-pointer inline-flex items-center gap-2">
                    <i class="ph ph-file-arrow-down"></i> {{ __('Bank Transfer CSV') }}
                </a>

                <button type="button" data-pay-open-batch class="btn-sm bg-success text-white border border-success hover:bg-success/90 cursor-pointer inline-flex items-center gap-2">
                    <i class="ph ph-money"></i> {{ __('Pay All (:n)', ['n' => $unpaidCount]) }}
                </button>
            </div>
        @endif
    @endif
</div>

<div class="bg-white border border-border-color rounded-md p-4">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-default text-[11px] uppercase font-medium">
                    <th class="text-left py-2 border-b border-border-color">{{ __('Employee') }}</th>
                    <th class="text-right py-2 border-b border-border-color">{{ __('Gross') }}</th>
                    <th class="text-right py-2 border-b border-border-color">{{ __('SGK Worker') }}</th>
                    <th class="text-right py-2 border-b border-border-color">{{ __('Income Tax') }}</th>
                    <th class="text-right py-2 border-b border-border-color">{{ __('Stamp') }}</th>
                    <th class="text-right py-2 border-b border-border-color">{{ __('Net') }}</th>
                    <th class="text-right py-2 border-b border-border-color">{{ __('Advance') }}</th>
                    <th class="text-right py-2 border-b border-border-color">{{ __('Cash Payable') }}</th>
                    <th class="text-right py-2 border-b border-border-color">{{ __('Employer Cost') }}</th>
                    <th class="text-left py-2 border-b border-border-color">{{ __('Status') }}</th>
                    <th class="py-2 border-b border-border-color"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($period->payslips as $slip)
                    @php $sgkTotal = bcadd((string) $slip->sgk_worker, (string) $slip->unemployment_worker, 4); @endphp
                    <tr class="border-b border-border-color">
                        <td class="py-2 text-gray-900">
                            {{ $slip->employee->first_name }} {{ $slip->employee->last_name }}
                            <div class="text-[11px] text-default">
                                {{ optional($slip->employee->department)->name ?? '—' }}
                                @if ($slip->employee->salary_expense_type === 'direct_labor')
                                    · <span class="text-info">720</span>
                                @else
                                    · <span class="text-warning">770</span>
                                @endif
                            </div>
                        </td>
                        <td class="py-2 text-right text-default">{{ number_format((float) $slip->gross_salary, 2, ',', '.') }}</td>
                        <td class="py-2 text-right text-default">{{ number_format((float) $sgkTotal, 2, ',', '.') }}</td>
                        <td class="py-2 text-right text-default">{{ number_format((float) $slip->income_tax, 2, ',', '.') }}</td>
                        <td class="py-2 text-right text-default">{{ number_format((float) $slip->stamp_tax, 2, ',', '.') }}</td>
                        <td class="py-2 text-right font-semibold text-success">{{ number_format((float) $slip->net_salary, 2, ',', '.') }}</td>
                        <td class="py-2 text-right text-warning">
                            {{ bccomp((string) $slip->advance_deducted, '0', 4) > 0 ? '−'.number_format((float) $slip->advance_deducted, 2, ',', '.') : '—' }}
                        </td>
                        <td class="py-2 text-right font-bold text-primary">{{ number_format((float) $slip->cashPayable(), 2, ',', '.') }}</td>
                        <td class="py-2 text-right text-danger">{{ number_format((float) $slip->total_employer_cost, 2, ',', '.') }}</td>
                        <td class="py-2">
                            <span class="text-[11px] px-2 py-0.5 rounded {{ $slip->status === 'paid' ? 'bg-success-transparent text-success' : 'bg-warning-transparent text-warning' }}">
                                {{ $slip->status === 'paid' ? __('Paid') : __('Unpaid') }}
                            </span>
                        </td>
                        <td class="py-2 text-right">
                            @if ($period->status === 'posted' && $slip->status === 'calculated')
                                <button type="button"
                                        data-pay-open-slip
                                        data-slip-id="{{ $slip->id }}"
                                        data-slip-name="{{ $slip->employee->first_name }} {{ $slip->employee->last_name }}"
                                        data-slip-amount="{{ number_format((float) $slip->cashPayable(), 2, ',', '.') }}"
                                        class="text-primary text-[12px] hover:underline cursor-pointer">
                                    {{ __('Pay') }}
                                </button>
                            @elseif ($slip->status === 'paid')
                                <span class="text-[11px] text-default">{{ optional($slip->paid_at)->format('d.m.Y') }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="11" class="py-6 text-center text-default">{{ __('No payslips generated yet. Click Calculate above.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Toplu ödeme modalı --}}
<div id="pay-batch-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4" role="dialog">
    <div class="bg-white rounded-md shadow-xl w-full" style="max-width: min(480px, calc(100vw - 32px));">
        <form method="POST" action="{{ route('app.hr.payroll.pay-all', $period) }}"
              onsubmit="return confirm('{{ __(':n payslips will be paid in a single journal entry. Continue?', ['n' => $period->payslips->where('status', 'calculated')->count()]) }}')">
            @csrf
            <div class="flex items-center justify-between border-b border-border-color px-4 py-3">
                <h2 class="text-base font-bold text-title inline-flex items-center gap-2">
                    <i class="ph ph-money"></i> {{ __('Pay All Payslips') }}
                </h2>
                <button type="button" data-pay-batch-close class="text-default hover:text-gray-900 cursor-pointer"><i class="ph ph-x text-lg"></i></button>
            </div>
            <div class="p-4 space-y-3">
                <p class="text-[12px] text-default mb-0">
                    {{ __(':n payslips will be paid in a single journal entry from the selected account.', ['n' => $period->payslips->where('status', 'calculated')->count()]) }}
                </p>
                <div>
                    <label class="text-[12px] text-default block mb-1">{{ __('Pay all from') }} *</label>
                    <select name="journal_id" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                        @foreach ($journals as $j)
                            <option value="{{ $j->id }}">{{ $j->name }} ({{ __(ucfirst($j->type)) }})</option>
                        @endforeach
                    </select>
                </div>
                <p class="text-[11px] text-default">
                    {{ __('Use this after you have sent the bank transfer file to the bank.') }}
                </p>
            </div>
            <div class="border-t border-border-color px-4 py-3 flex items-center justify-end gap-2">
                <button type="button" data-pay-batch-close class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light cursor-pointer">{{ __('Cancel') }}</button>
                <button type="submit" class="btn-sm bg-success text-white border border-success hover:bg-success/90 cursor-pointer inline-flex items-center gap-2">
                    <i class="ph ph-check"></i> {{ __('Confirm Batch Payment') }}
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Tek personel ödeme modalı --}}
<div id="pay-slip-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4" role="dialog">
    <div class="bg-white rounded-md shadow-xl w-full" style="max-width: min(440px, calc(100vw - 32px));">
        <form id="pay-slip-form" method="POST">
            @csrf
            <div class="flex items-center justify-between border-b border-border-color px-4 py-3">
                <h2 class="text-base font-bold text-title inline-flex items-center gap-2">
                    <i class="ph ph-money"></i> <span id="pay-slip-title">{{ __('Pay Payslip') }}</span>
                </h2>
                <button type="button" data-pay-slip-close class="text-default hover:text-gray-900 cursor-pointer"><i class="ph ph-x text-lg"></i></button>
            </div>
            <div class="p-4 space-y-3">
                <div class="bg-light rounded-md p-3 text-sm">
                    <div class="text-default text-[11px] uppercase">{{ __('Cash Payable') }}</div>
                    <div id="pay-slip-amount" class="text-xl font-bold text-primary">—</div>
                </div>
                <div>
                    <label class="text-[12px] text-default block mb-1">{{ __('Pay from') }} *</label>
                    <select name="journal_id" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                        @foreach ($journals as $j)
                            <option value="{{ $j->id }}">{{ $j->name }} ({{ __(ucfirst($j->type)) }})</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="border-t border-border-color px-4 py-3 flex items-center justify-end gap-2">
                <button type="button" data-pay-slip-close class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light cursor-pointer">{{ __('Cancel') }}</button>
                <button type="submit" class="btn-sm bg-success text-white border border-success hover:bg-success/90 cursor-pointer inline-flex items-center gap-2">
                    <i class="ph ph-check"></i> {{ __('Confirm Payment') }}
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const batchModal = document.getElementById('pay-batch-modal');
    const slipModal = document.getElementById('pay-slip-modal');
    const slipForm = document.getElementById('pay-slip-form');
    const slipTemplate = @json(url('/app/hr/payroll/payslips')) + '/{id}/pay';

    const show = (el) => { el.classList.remove('hidden'); el.classList.add('flex'); };
    const hide = (el) => { el.classList.remove('flex'); el.classList.add('hidden'); };

    document.querySelectorAll('[data-pay-open-batch]').forEach((b) => b.addEventListener('click', () => show(batchModal)));
    document.querySelectorAll('[data-pay-batch-close]').forEach((b) => b.addEventListener('click', () => hide(batchModal)));
    batchModal.addEventListener('click', (e) => { if (e.target === batchModal) hide(batchModal); });

    document.querySelectorAll('[data-pay-open-slip]').forEach((b) => b.addEventListener('click', () => {
        slipForm.action = slipTemplate.replace('{id}', b.dataset.slipId);
        document.getElementById('pay-slip-title').textContent = @json(__('Pay')) + ' — ' + b.dataset.slipName;
        document.getElementById('pay-slip-amount').textContent = b.dataset.slipAmount;
        show(slipModal);
    }));
    document.querySelectorAll('[data-pay-slip-close]').forEach((b) => b.addEventListener('click', () => hide(slipModal)));
    slipModal.addEventListener('click', (e) => { if (e.target === slipModal) hide(slipModal); });
})();
</script>
@endsection
