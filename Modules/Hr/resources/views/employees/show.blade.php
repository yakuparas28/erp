@extends('app.layouts.app')

@section('title', $employee->full_name)

@section('content')
<div class="mb-3 lg:mb-6">
    <nav class="text-xs text-default mb-1">
        <a href="{{ route('app.hr.employees.index') }}" class="hover:text-primary">{{ __('Employees') }}</a>
        <span class="mx-1">/</span>
        <span class="text-title font-semibold">{{ $employee->full_name }}</span>
    </nav>
</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-3">

    <div class="lg:col-span-4 space-y-3">
        <div class="bg-white border border-border-color rounded-md p-6 text-center">
            <div class="size-24 rounded-full mx-auto bg-primary-transparent text-primary flex items-center justify-center text-3xl font-bold border-2 border-border-color">
                {{ strtoupper(substr($employee->first_name, 0, 1)) }}{{ strtoupper(substr($employee->last_name, 0, 1)) }}
            </div>
            <h2 class="text-base font-semibold text-title mt-3 mb-0">{{ $employee->full_name }}</h2>
            <p class="text-sm text-default mb-2">{{ $employee->title ?? '—' }}</p>
            @if ($employee->is_active)
                <span class="inline-block text-xs bg-success text-white rounded-md px-2 py-0.5">{{ __('Active') }}</span>
            @else
                <span class="inline-block text-xs bg-danger text-white rounded-md px-2 py-0.5">{{ __('Inactive') }}</span>
            @endif

            <div class="border-t border-border-color mt-4 pt-4 space-y-2 text-sm text-left">
                <div class="flex justify-between">
                    <dt class="text-default">{{ __('Employee ID') }}</dt>
                    <dd class="text-title font-mono">EMP-{{ str_pad((string) $employee->id, 5, '0', STR_PAD_LEFT) }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-default">{{ __('Email') }}</dt>
                    <dd class="text-title text-xs">{{ $employee->user?->email ?? '—' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-default">{{ __('Mobile') }}</dt>
                    <dd class="text-title">{{ $employee->mobile ?? '—' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-default">{{ __('Department') }}</dt>
                    <dd class="text-title">{{ $employee->department?->name ?? '—' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-default">{{ __('Manager') }}</dt>
                    <dd class="text-title">{{ $employee->manager?->full_name ?? '—' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-default">{{ __('Hire Date') }}</dt>
                    <dd class="text-title font-mono">{{ $employee->hire_date?->format('d.m.Y') ?? '—' }}</dd>
                </div>
                <div class="flex justify-between pt-2 border-t border-border-color">
                    <dt class="text-default font-semibold">{{ __('Leave Balance') }}</dt>
                    <dd class="text-title font-bold">{{ $employee->annual_leave_balance }} {{ __('days') }}</dd>
                </div>
            </div>
        </div>

        @if ($employee->subordinates->isNotEmpty())
            <div class="bg-white border border-border-color rounded-md">
                <h3 class="text-sm font-semibold text-title mb-3">{{ __('Direct Reports') }} ({{ $employee->subordinates->count() }})</h3>
                <ul class="space-y-2 text-sm">
                    @foreach ($employee->subordinates as $sub)
                        <li class="flex items-center justify-between">
                            <a href="{{ route('app.hr.employees.show', $sub) }}" class="text-title hover:text-primary">{{ $sub->full_name }}</a>
                            <span class="text-xs text-default">{{ $sub->title ?? '—' }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>

    <div class="lg:col-span-8 space-y-3">
        <div class="bg-white border border-border-color rounded-md">
            <div class="border-b border-border-color px-4">
                <nav class="flex items-center gap-6 text-sm">
                    <button type="button" data-hs-tab="#tab-about" class="py-3 border-b-2 border-primary text-primary -mb-px font-semibold">{{ __('About') }}</button>
                    <button type="button" data-hs-tab="#tab-leaves" class="py-3 border-b-2 border-transparent text-default hover:text-title">{{ __('Recent Leaves') }}</button>
                </nav>
            </div>

            <div id="tab-about" class="p-4 space-y-6">
                <div>
                    <h3 class="text-sm font-semibold text-title mb-3">{{ __('Personal Information') }}</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                        <div><div class="text-default mb-1">{{ __('First Name') }}</div><div class="text-gray-900 font-medium">{{ $employee->first_name }}</div></div>
                        <div><div class="text-default mb-1">{{ __('Last Name') }}</div><div class="text-gray-900 font-medium">{{ $employee->last_name }}</div></div>
                        <div><div class="text-default mb-1">{{ __('Birth Date') }}</div><div class="text-gray-900 font-medium">{{ $employee->birth_date?->format('d.m.Y') ?? '—' }}</div></div>
                        <div><div class="text-default mb-1">{{ __('National ID') }}</div><div class="text-gray-900 font-medium">{{ $employee->national_id ?? '—' }}</div></div>
                        <div><div class="text-default mb-1">{{ __('Phone') }}</div><div class="text-gray-900 font-medium">{{ $employee->phone ?? '—' }}</div></div>
                        <div><div class="text-default mb-1">{{ __('Mobile') }}</div><div class="text-gray-900 font-medium">{{ $employee->mobile ?? '—' }}</div></div>
                    </div>
                </div>

                <div>
                    <h3 class="text-sm font-semibold text-title mb-3">{{ __('Employment Details') }}</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                        <div><div class="text-default mb-1">{{ __('Title') }}</div><div class="text-gray-900 font-medium">{{ $employee->title ?? '—' }}</div></div>
                        <div><div class="text-default mb-1">{{ __('Department') }}</div><div class="text-gray-900 font-medium">{{ $employee->department?->name ?? '—' }}</div></div>
                        <div><div class="text-default mb-1">{{ __('Manager') }}</div><div class="text-gray-900 font-medium">{{ $employee->manager?->full_name ?? '—' }}</div></div>
                        <div><div class="text-default mb-1">{{ __('Hire Date') }}</div><div class="text-gray-900 font-medium">{{ $employee->hire_date?->format('d.m.Y') ?? '—' }}</div></div>
                        <div><div class="text-default mb-1">{{ __('Termination Date') }}</div><div class="text-gray-900 font-medium">{{ $employee->termination_date?->format('d.m.Y') ?? '—' }}</div></div>
                        <div><div class="text-default mb-1">{{ __('Second-level approval required') }}</div><div class="text-gray-900 font-medium">{{ $employee->second_level_approval_required ? __('Yes') : __('No') }}</div></div>
                    </div>
                </div>

                @if ($employee->notes)
                    <div>
                        <h3 class="text-sm font-semibold text-title mb-3">{{ __('Notes') }}</h3>
                        <p class="text-sm text-gray-900 whitespace-pre-line">{{ $employee->notes }}</p>
                    </div>
                @endif
            </div>

            <div id="tab-leaves" class="p-4" hidden>
                @if ($recentLeaves->isEmpty())
                    <p class="text-sm text-default text-center py-6">{{ __('No leave records.') }}</p>
                @else
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-border-color text-default text-xs">
                                <th class="text-left py-2 px-2">{{ __('Type') }}</th>
                                <th class="text-left py-2 px-2">{{ __('Dates') }}</th>
                                <th class="text-right py-2 px-2">{{ __('Days') }}</th>
                                <th class="text-left py-2 px-2">{{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recentLeaves as $l)
                                <tr class="border-b border-border-color">
                                    <td class="py-2 px-2">{{ $l->leaveType->name }}</td>
                                    <td class="py-2 px-2">{{ $l->start_date->format('d.m.Y') }} – {{ $l->end_date->format('d.m.Y') }}</td>
                                    <td class="py-2 px-2 text-right">{{ $l->total_days }}</td>
                                    <td class="py-2 px-2">
                                        @php $s = $l->approval?->status ?? 'pending'; @endphp
                                        @if ($s === 'approved')
                                            <span class="text-[11px] bg-success-transparent text-success px-2 py-0.5 rounded">{{ __('Approved') }}</span>
                                        @elseif ($s === 'rejected')
                                            <span class="text-[11px] bg-danger-transparent text-danger px-2 py-0.5 rounded">{{ __('Rejected') }}</span>
                                        @elseif ($s === 'cancelled')
                                            <span class="text-[11px] bg-light text-default px-2 py-0.5 rounded">{{ __('Cancelled') }}</span>
                                        @else
                                            <span class="text-[11px] bg-warning-transparent text-warning px-2 py-0.5 rounded">{{ __('Pending') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('app.hr.employees.index') }}" class="btn-sm bg-white border border-border-color text-gray-900 inline-flex items-center gap-2 hover:bg-light">
                <i class="ph ph-arrow-left"></i> {{ __('Back to List') }}
            </a>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('[data-hs-tab]').forEach((btn) => {
    btn.addEventListener('click', () => {
        const target = btn.getAttribute('data-hs-tab');
        document.querySelectorAll('[id^="tab-"]').forEach((p) => p.hidden = ('#' + p.id) !== target);
        document.querySelectorAll('[data-hs-tab]').forEach((b) => {
            const active = b.getAttribute('data-hs-tab') === target;
            b.classList.toggle('border-primary', active);
            b.classList.toggle('text-primary', active);
            b.classList.toggle('font-semibold', active);
            b.classList.toggle('border-transparent', !active);
            b.classList.toggle('text-default', !active);
        });
    });
});
</script>
@endsection
