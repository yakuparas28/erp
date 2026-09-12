@extends('app.layouts.app')

@section('title', __('Employees'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <div>
        <h1 class="text-gray-900 text-xl font-bold mb-1">{{ __('Employees') }}</h1>
        <p class="text-sm text-default mb-0">{{ __('Link each system user to their HR profile (department, manager, leave balance).') }}</p>
    </div>
    <button type="button" data-hs-overlay="#add-employee-modal" class="btn-sm bg-dark text-white border border-dark inline-flex items-center gap-2 hover:bg-primary-hover hover:border-primary-hover cursor-pointer">
        <i class="ph ph-plus"></i> {{ __('New Employee') }}
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

<div class="bg-white border border-border-color rounded-md">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-sm text-default border-b border-border-color">
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Name') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Title') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Department') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Manager') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Email') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Leave Balance') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($employees as $employee)
                    <tr class="border-b border-border-color">
                        <td class="py-2.5 px-3 text-sm font-semibold text-title">
                            {{ $employee->full_name }}
                            @if (! $employee->is_active)
                                <span class="ml-2 text-xs bg-danger-transparent text-danger px-1.5 py-0.5 rounded">{{ __('Inactive') }}</span>
                            @endif
                        </td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $employee->title ?? '—' }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $employee->department?->name ?? '—' }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $employee->manager?->full_name ?? '—' }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $employee->user?->email ?? '—' }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $employee->annual_leave_balance }}</td>
                        <td class="py-2.5 px-3">
                            <div class="flex items-center gap-2">
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
                    <tr><td colspan="7" class="py-8 text-center text-sm text-default">{{ __('No employees yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div id="add-employee-modal" class="hs-overlay hidden fixed inset-0 z-50 overflow-x-hidden overflow-y-auto pointer-events-none">
    <div class="hs-overlay-open:opacity-100 hs-overlay-open:duration-500 opacity-0 transition-all sm:max-w-lg sm:w-full m-3 sm:mx-auto min-h-[calc(100%-56px)] flex items-center">
        <div class="pointer-events-auto bg-white border border-border-color rounded-md w-full">
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
            <div class="pointer-events-auto bg-white border border-border-color rounded-md w-full">
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
