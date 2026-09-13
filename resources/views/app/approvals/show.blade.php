@extends('app.layouts.app')

@section('title', $workflow->name)

@section('content')
<div class="mb-3 lg:mb-6">
    <nav class="text-xs text-default mb-1">
        <a href="{{ route('app.approval-workflows.index') }}" class="hover:text-primary">{{ __('Approval Workflows') }}</a>
        <span class="mx-1">/</span>
        <span class="text-title font-semibold">{{ $workflow->name }}</span>
    </nav>
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

    <div class="bg-white border border-border-color rounded-md">
        <div class="p-4 border-b border-border-color">
            <h2 class="text-base font-bold text-title mb-0">{{ __('Workflow') }}</h2>
        </div>
        <form method="POST" action="{{ route('app.approval-workflows.update', $workflow) }}" class="p-4 space-y-3">
            @csrf
            @method('PATCH')
            <div>
                <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Name') }} <span class="text-danger">*</span></label>
                <input type="text" name="name" required maxlength="100" value="{{ $workflow->name }}" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
            </div>
            <div>
                <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Subject Type') }} <span class="text-danger">*</span></label>
                <select name="subject_type" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                    @foreach ($subjectTypes as $key => $label)
                        <option value="{{ $key }}" @selected($workflow->subject_type === $key)>{{ $label }} ({{ $key }})</option>
                    @endforeach
                </select>
            </div>
            <label class="flex items-center gap-2 text-sm text-gray-900">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" @checked($workflow->is_active)>
                {{ __('Active') }}
            </label>
            <button type="submit" class="btn-sm bg-dark text-white border border-dark w-full inline-flex items-center justify-center gap-2 hover:bg-primary-hover">
                <i class="ph ph-floppy-disk"></i> {{ __('Save') }}
            </button>
        </form>
    </div>

    <div class="lg:col-span-2 bg-white border border-border-color rounded-md">
        <div class="p-4 border-b border-border-color flex items-center justify-between">
            <h2 class="text-base font-bold text-title mb-0">{{ __('Approval Steps') }}</h2>
        </div>

        <form method="POST" action="{{ route('app.approval-workflows.steps.store', $workflow) }}" class="p-4 grid grid-cols-12 gap-2 border-b border-border-color">
            @csrf
            <div class="col-span-2">
                <label class="text-xs font-semibold text-gray-900 mb-1 block">{{ __('Sequence') }}</label>
                <input type="number" name="sequence" min="1" required value="{{ ($workflow->steps->max('sequence') ?? 0) + 1 }}" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
            </div>
            <div class="col-span-4">
                <label class="text-xs font-semibold text-gray-900 mb-1 block">{{ __('Approver Type') }}</label>
                <select name="approver_type" required id="approver-type-input" onchange="document.getElementById('approver-value-input').placeholder = this.value === 'role' ? '{{ __('Role name') }}' : this.value === 'user' ? '{{ __('User ID') }}' : '—';" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                    <option value="manager">{{ __('Direct Manager') }}</option>
                    <option value="department_manager">{{ __('Department Manager') }}</option>
                    <option value="role">{{ __('Role') }}</option>
                    <option value="user">{{ __('Specific User') }}</option>
                </select>
            </div>
            <div class="col-span-4">
                <label class="text-xs font-semibold text-gray-900 mb-1 block">{{ __('Value') }}</label>
                <input type="text" name="approver_value" maxlength="100" id="approver-value-input" placeholder="—" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
            </div>
            <div class="col-span-2 flex items-end">
                <button type="submit" class="btn-sm bg-primary text-white border border-primary w-full inline-flex items-center justify-center gap-1 hover:bg-primary-hover">
                    <i class="ph ph-plus"></i>
                </button>
            </div>
        </form>

        @if ($workflow->steps->isEmpty())
            <p class="text-xs text-default text-center py-6">{{ __('No steps defined. Add at least one so requests can be submitted.') }}</p>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-sm text-default border-b border-border-color">
                        <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Sequence') }}</th>
                        <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Approver Type') }}</th>
                        <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Value') }}</th>
                        <th class="text-right py-2 px-3 font-semibold text-gray-900">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($workflow->steps as $step)
                        <tr class="border-b border-border-color">
                            <td class="py-2.5 px-3 font-mono">{{ $step->sequence }}</td>
                            <td class="py-2.5 px-3">
                                @php $labels = ['manager' => __('Direct Manager'), 'department_manager' => __('Department Manager'), 'role' => __('Role'), 'user' => __('Specific User')]; @endphp
                                <span class="text-[11px] bg-primary-transparent text-primary px-2 py-0.5 rounded">{{ $labels[$step->approver_type] ?? $step->approver_type }}</span>
                            </td>
                            <td class="py-2.5 px-3">{{ $step->approver_value ?? '—' }}</td>
                            <td class="py-2.5 px-3 text-right">
                                <form method="POST" action="{{ route('app.approval-workflows.steps.destroy', [$workflow, $step]) }}" onsubmit="return confirm('{{ __('Delete this step?') }}')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="size-7 rounded-md border border-border-color inline-flex items-center justify-center text-danger hover:bg-light" title="{{ __('Delete') }}">
                                        <i class="ph ph-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if ($roles->isNotEmpty())
            <div class="p-3 border-t border-border-color bg-light text-xs text-default">
                <strong>{{ __('Available roles') }}:</strong> {{ $roles->implode(', ') }}
            </div>
        @endif
    </div>

</div>
@endsection
