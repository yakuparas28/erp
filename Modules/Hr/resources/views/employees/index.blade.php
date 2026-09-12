@extends('app.layouts.app')

@section('title', __('Employees'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <h1 class="text-gray-900 text-xl font-bold mb-0">{{ __('Employees') }}</h1>
    <button type="button" data-hs-overlay="#add-employee-modal" class="btn-sm bg-dark text-white border border-dark inline-flex items-center gap-2 hover:bg-primary-hover hover:border-primary-hover cursor-pointer">
        <i class="ph ph-plus"></i> {{ __('Add New') }}
    </button>
</div>

@if (session('status'))
    <div class="bg-success-transparent text-success border border-success rounded-md px-4 py-3 text-sm mb-4">{{ session('status') }}</div>
@endif
@if ($errors->any())
    <div class="bg-danger-transparent text-danger border border-danger rounded-md px-4 py-3 text-sm mb-4">
        @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
    </div>
@endif

<div class="bg-white border border-border-color rounded-md p-4">
    <p class="text-sm text-default mb-3">{{ __('Link each system user to their HR profile (department, manager, leave balance).') }}</p>
    <div class="overflow-x-auto -mx-4">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-y border-border-color bg-light">
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Employee ID') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Employee') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Email') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Title') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Department') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Manager') }}</th>
                    <th class="text-right py-2 px-3 font-semibold text-gray-900">{{ __('Leave Balance') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Status') }}</th>
                    <th class="text-center py-2 px-3 font-semibold text-gray-900">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($employees as $employee)
                    <tr class="border-b border-border-color hover:bg-light/50">
                        <td class="py-3 px-3 font-mono text-xs">
                            <a href="{{ route('app.hr.employees.show', $employee) }}" class="text-primary hover:underline">EMP-{{ str_pad((string) $employee->id, 5, '0', STR_PAD_LEFT) }}</a>
                        </td>
                        <td class="py-3 px-3">
                            <div class="flex items-center gap-2">
                                <div class="size-8 rounded-full bg-primary-transparent text-primary flex items-center justify-center text-xs font-semibold">
                                    {{ strtoupper(substr($employee->first_name, 0, 1)) }}{{ strtoupper(substr($employee->last_name, 0, 1)) }}
                                </div>
                                <a href="{{ route('app.hr.employees.show', $employee) }}" class="font-semibold text-title hover:text-primary">{{ $employee->full_name }}</a>
                            </div>
                        </td>
                        <td class="py-3 px-3 text-default text-xs">{{ $employee->user?->email ?? '—' }}</td>
                        <td class="py-3 px-3 text-default">{{ $employee->title ?? '—' }}</td>
                        <td class="py-3 px-3 text-default">{{ $employee->department?->name ?? '—' }}</td>
                        <td class="py-3 px-3 text-default">{{ $employee->manager?->full_name ?? '—' }}</td>
                        <td class="py-3 px-3 text-right font-semibold">{{ $employee->annual_leave_balance }}</td>
                        <td class="py-3 px-3">
                            @if ($employee->is_active)
                                <span class="text-[11px] bg-success-transparent text-success px-2 py-0.5 rounded">{{ __('Active') }}</span>
                            @else
                                <span class="text-[11px] bg-danger-transparent text-danger px-2 py-0.5 rounded">{{ __('Inactive') }}</span>
                            @endif
                        </td>
                        <td class="py-3 px-3">
                            <div class="flex items-center justify-center gap-1">
                                <a href="{{ route('app.hr.employees.show', $employee) }}" class="size-7 rounded-md border border-border-color flex items-center justify-center text-gray-900 hover:bg-light" title="{{ __('View') }}">
                                    <i class="ph ph-eye"></i>
                                </a>
                                <button type="button" data-hs-overlay="#edit-employee-modal-{{ $employee->id }}" class="size-7 rounded-md border border-border-color flex items-center justify-center text-gray-900 hover:bg-light cursor-pointer" title="{{ __('Edit') }}">
                                    <i class="ph ph-pencil-simple"></i>
                                </button>
                                <form method="POST" action="{{ route('app.hr.employees.destroy', $employee) }}" onsubmit="return confirm('{{ __('Delete this employee?') }}')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="size-7 rounded-md border border-border-color flex items-center justify-center text-danger hover:bg-light cursor-pointer" title="{{ __('Delete') }}">
                                        <i class="ph ph-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="py-8 text-center text-sm text-default">{{ __('No employees yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div id="add-employee-modal" class="hs-overlay hidden fixed inset-0 z-50 overflow-x-hidden overflow-y-auto pointer-events-none">
    <div class="hs-overlay-open:opacity-100 hs-overlay-open:duration-500 opacity-0 transition-all sm:max-w-lg sm:w-full m-3 sm:mx-auto min-h-[calc(100%-56px)] flex items-center">
        <div class="pointer-events-auto bg-white border border-border-color rounded-md w-full shadow">
            <form method="POST" action="{{ route('app.hr.employees.store') }}">
                @csrf
                @include('hr::employees._form-fields', ['availableUsers' => $availableUsers, 'departments' => $departments, 'managers' => $managers, 'employee' => null])
                <div class="flex items-center justify-end gap-2 p-4 border-t border-border-color">
                    <button type="button" data-hs-overlay="#add-employee-modal" class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover">{{ __('Save') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

@foreach ($employees as $employee)
    <div id="edit-employee-modal-{{ $employee->id }}" class="hs-overlay hidden fixed inset-0 z-50 overflow-x-hidden overflow-y-auto pointer-events-none">
        <div class="hs-overlay-open:opacity-100 hs-overlay-open:duration-500 opacity-0 transition-all sm:max-w-lg sm:w-full m-3 sm:mx-auto min-h-[calc(100%-56px)] flex items-center">
            <div class="pointer-events-auto bg-white border border-border-color rounded-md w-full shadow">
                <form method="POST" action="{{ route('app.hr.employees.update', $employee) }}">
                    @csrf
                    @method('PATCH')
                    @include('hr::employees._form-fields', ['availableUsers' => $availableUsers->push($employee->user)->unique('id'), 'departments' => $departments, 'managers' => $managers->reject(fn ($m) => $m->id === $employee->id), 'employee' => $employee])
                    <div class="flex items-center justify-end gap-2 p-4 border-t border-border-color">
                        <button type="button" data-hs-overlay="#edit-employee-modal-{{ $employee->id }}" class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light">{{ __('Cancel') }}</button>
                        <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover">{{ __('Update') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endforeach
@endsection
