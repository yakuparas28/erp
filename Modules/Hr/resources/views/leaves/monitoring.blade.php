@extends('app.layouts.app')

@section('title', __('Leave Monitoring'))

@section('content')
<div class="mb-3 lg:mb-6">
    <h1 class="text-gray-900 text-xl font-bold mb-1">{{ __('Leave Monitoring') }}</h1>
    <p class="text-sm text-default mb-0">{{ __('All leave requests across the organization with their approval history.') }}</p>
</div>

<div class="bg-white border border-border-color rounded-md overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-border-color text-default">
                <th class="text-left py-2 px-3">{{ __('Employee') }}</th>
                <th class="text-left py-2 px-3">{{ __('Department') }}</th>
                <th class="text-left py-2 px-3">{{ __('Type') }}</th>
                <th class="text-left py-2 px-3">{{ __('Dates') }}</th>
                <th class="text-right py-2 px-3">{{ __('Days') }}</th>
                <th class="text-left py-2 px-3">{{ __('Status') }}</th>
                <th class="text-left py-2 px-3">{{ __('Last Action') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($requests as $r)
                <tr class="border-b border-border-color">
                    <td class="py-2.5 px-3 font-semibold">{{ $r->employee->full_name }}</td>
                    <td class="py-2.5 px-3 text-default">{{ $r->employee->department?->name ?? '—' }}</td>
                    <td class="py-2.5 px-3">{{ $r->leaveType->name }}</td>
                    <td class="py-2.5 px-3">{{ $r->start_date->format('d.m.Y') }} – {{ $r->end_date->format('d.m.Y') }}</td>
                    <td class="py-2.5 px-3 text-right">{{ $r->total_days }}</td>
                    <td class="py-2.5 px-3">
                        @php $s = $r->approval?->status ?? '—'; @endphp
                        @if ($s === 'approved')
                            <span class="text-xs bg-success-transparent text-success px-2 py-0.5 rounded">{{ __('Approved') }}</span>
                        @elseif ($s === 'rejected')
                            <span class="text-xs bg-danger-transparent text-danger px-2 py-0.5 rounded">{{ __('Rejected') }}</span>
                        @elseif ($s === 'cancelled')
                            <span class="text-xs bg-light text-default px-2 py-0.5 rounded">{{ __('Cancelled') }}</span>
                        @else
                            <span class="text-xs bg-warning-transparent text-warning px-2 py-0.5 rounded">{{ __('Pending') }}</span>
                        @endif
                    </td>
                    <td class="py-2.5 px-3 text-default">
                        @php $last = $r->approval?->actions->first(); @endphp
                        @if ($last)
                            {{ $last->actor?->name }} — {{ $last->created_at->format('d.m.Y H:i') }}
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-8 text-center text-default">{{ __('No leave records.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
