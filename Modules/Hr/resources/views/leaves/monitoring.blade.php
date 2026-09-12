@extends('app.layouts.app')

@section('title', __('Leave Monitoring'))

@section('content')
<div class="mb-3 lg:mb-6">
    <h1 class="text-gray-900 text-xl font-bold mb-0">{{ __('Leave Monitoring') }}</h1>
    <p class="text-sm text-default mb-0 mt-1">{{ __('All leave requests across the organization with their approval history.') }}</p>
</div>

<div class="bg-white border border-border-color rounded-md">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-sm text-default border-b border-border-color">
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('ID') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Employee') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Department') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Type') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Dates') }}</th>
                    <th class="text-right py-2 px-3 font-semibold text-gray-900">{{ __('Days') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Status') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Last Action') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($requests as $r)
                    <tr class="border-b border-border-color ">
                        <td class="py-2.5 px-3 font-mono text-xs text-primary">LV-{{ str_pad((string) $r->id, 5, '0', STR_PAD_LEFT) }}</td>
                        <td class="py-2.5 px-3">
                            <div class="flex items-center gap-2">
                                <div class="size-7 rounded-full bg-primary-transparent text-primary flex items-center justify-center text-[10px] font-semibold">
                                    {{ strtoupper(substr($r->employee->first_name, 0, 1)) }}{{ strtoupper(substr($r->employee->last_name, 0, 1)) }}
                                </div>
                                <a href="{{ route('app.hr.employees.show', $r->employee) }}" class="font-semibold text-title hover:text-primary">{{ $r->employee->full_name }}</a>
                            </div>
                        </td>
                        <td class="py-2.5 px-3 text-default">{{ $r->employee->department?->name ?? '—' }}</td>
                        <td class="py-2.5 px-3">{{ $r->leaveType->name }}</td>
                        <td class="py-2.5 px-3">{{ $r->start_date->format('d.m.Y') }} – {{ $r->end_date->format('d.m.Y') }}</td>
                        <td class="py-2.5 px-3 text-right font-semibold">{{ $r->total_days }}</td>
                        <td class="py-2.5 px-3">
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
                        <td class="py-2.5 px-3 text-default text-xs">
                            @php $last = $r->approval?->actions->first(); @endphp
                            @if ($last)
                                {{ $last->actor?->name }}<br><span class="text-[10px]">{{ $last->created_at->format('d.m.Y H:i') }}</span>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="py-8 text-center text-default">{{ __('No leave records.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
