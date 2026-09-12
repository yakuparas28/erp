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
                <th class="text-left py-2 px-3">{{ __('Department') }}</th>
                <th class="text-left py-2 px-3">{{ __('Type') }}</th>
                <th class="text-left py-2 px-3">{{ __('Start') }}</th>
                <th class="text-left py-2 px-3">{{ __('End') }}</th>
                <th class="text-right py-2 px-3">{{ __('Days') }}</th>
                <th class="text-left py-2 px-3">{{ __('Reason') }}</th>
                <th class="text-left py-2 px-3">{{ __('Actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($requests as $r)
                <tr class="border-b border-border-color">
                    <td class="py-2.5 px-3 font-semibold text-title">{{ $r->employee->full_name }}</td>
                    <td class="py-2.5 px-3">{{ $r->employee->department?->name ?? '—' }}</td>
                    <td class="py-2.5 px-3">{{ $r->leaveType->name }}</td>
                    <td class="py-2.5 px-3">{{ $r->start_date->format('d.m.Y') }}</td>
                    <td class="py-2.5 px-3">{{ $r->end_date->format('d.m.Y') }}</td>
                    <td class="py-2.5 px-3 text-right">{{ $r->total_days }}</td>
                    <td class="py-2.5 px-3 text-default">{{ Str::limit($r->reason, 60) }}</td>
                    <td class="py-2.5 px-3">
                        <div class="flex items-center gap-2">
                            <form method="POST" action="{{ route('app.hr.leave-approvals.approve', $r) }}">
                                @csrf
                                <button type="submit" class="btn-xs bg-success-transparent text-success border border-success px-3 py-1 rounded hover:bg-success hover:text-white">{{ __('Approve') }}</button>
                            </form>
                            <form method="POST" action="{{ route('app.hr.leave-approvals.reject', $r) }}" onsubmit="return confirm('{{ __('Reject this leave?') }}')">
                                @csrf
                                <button type="submit" class="btn-xs bg-danger-transparent text-danger border border-danger px-3 py-1 rounded hover:bg-danger hover:text-white">{{ __('Reject') }}</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="py-8 text-center text-default">{{ __('No pending approvals.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
