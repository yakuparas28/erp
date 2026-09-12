@if (session('status'))
    <div class="bg-success-transparent text-success border border-success rounded-md px-4 py-3 text-sm mb-4">{{ session('status') }}</div>
@endif
@if ($errors->any())
    <div class="bg-danger-transparent text-danger border border-danger rounded-md px-4 py-3 text-sm mb-4">
        @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
    </div>
@endif

<div class="bg-white border border-border-color rounded-md">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-sm text-default border-b border-border-color">
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('ID') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Employee') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Department') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Type') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('From') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('To') }}</th>
                    <th class="text-right py-2 px-3 font-semibold text-gray-900">{{ __('Days') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Reason') }}</th>
                    <th class="text-center py-2 px-3 font-semibold text-gray-900">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($requests as $r)
                    <tr class="border-b border-border-color ">
                        <td class="py-2.5 px-3 font-mono text-xs text-primary">LV-{{ str_pad((string) $r->id, 5, '0', STR_PAD_LEFT) }}</td>
                        <td class="py-2.5 px-3">
                            <div class="flex items-center gap-2">
                                <div class="size-8 rounded-full bg-primary-transparent text-primary flex items-center justify-center text-xs font-semibold">
                                    {{ strtoupper(substr($r->employee->first_name, 0, 1)) }}{{ strtoupper(substr($r->employee->last_name, 0, 1)) }}
                                </div>
                                <a href="{{ route('app.hr.employees.show', $r->employee) }}" class="font-semibold text-title hover:text-primary">{{ $r->employee->full_name }}</a>
                            </div>
                        </td>
                        <td class="py-2.5 px-3 text-default">{{ $r->employee->department?->name ?? '—' }}</td>
                        <td class="py-2.5 px-3">{{ $r->leaveType->name }}</td>
                        <td class="py-2.5 px-3">{{ $r->start_date->format('d.m.Y') }}</td>
                        <td class="py-2.5 px-3">{{ $r->end_date->format('d.m.Y') }}</td>
                        <td class="py-2.5 px-3 text-right font-semibold">{{ $r->total_days }}</td>
                        <td class="py-2.5 px-3 text-default text-xs">{{ Str::limit($r->reason, 60) }}</td>
                        <td class="py-2.5 px-3">
                            <div class="flex items-center justify-center gap-1">
                                <form method="POST" action="{{ route('app.hr.leave-approvals.approve', $r) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="size-7 rounded-md border border-success text-success inline-flex items-center justify-center hover:bg-success hover:text-white" title="{{ __('Approve') }}">
                                        <i class="ph ph-check"></i>
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('app.hr.leave-approvals.reject', $r) }}" onsubmit="return confirm('{{ __('Reject this leave?') }}')" class="inline">
                                    @csrf
                                    <button type="submit" class="size-7 rounded-md border border-danger text-danger inline-flex items-center justify-center hover:bg-danger hover:text-white" title="{{ __('Reject') }}">
                                        <i class="ph ph-x"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="py-8 text-center text-default">{{ __('No pending approvals.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
