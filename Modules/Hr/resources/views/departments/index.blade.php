@extends('app.layouts.app')

@section('title', __('Departments'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <div>
        <h1 class="text-gray-900 text-xl font-bold mb-1">{{ __('Departments') }}</h1>
        <p class="text-sm text-default mb-0">{{ __('Departments can be nested and assigned a manager (an employee).') }}</p>
    </div>
    <button type="button" data-hs-overlay="#add-department-modal" class="btn-sm bg-dark text-white border border-dark inline-flex items-center gap-2 hover:bg-primary-hover hover:border-primary-hover cursor-pointer">
        <i class="ph ph-plus"></i> {{ __('New Department') }}
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
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Parent') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Manager') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Sub-departments') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($departments as $department)
                    <tr class="border-b border-border-color">
                        <td class="py-2.5 px-3 text-sm font-semibold text-title">{{ $department->name }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $department->parent?->name ?? '—' }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $department->manager?->full_name ?? '—' }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $department->children->count() }}</td>
                        <td class="py-2.5 px-3">
                            <div class="flex items-center gap-2">
                                <button type="button" data-hs-overlay="#edit-department-modal-{{ $department->id }}" class="size-7 rounded-md border border-border-color flex items-center justify-center text-gray-900 hover:bg-light cursor-pointer" title="{{ __('Edit') }}">
                                    <i class="ph ph-pencil-simple"></i>
                                </button>
                                <form method="POST" action="{{ route('app.hr.departments.destroy', $department) }}" onsubmit="return confirm('{{ __('Delete this department?') }}')">
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
                    <tr><td colspan="5" class="py-8 text-center text-sm text-default">{{ __('No departments yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div id="add-department-modal" class="hs-overlay hidden fixed inset-0 z-50 overflow-x-hidden overflow-y-auto pointer-events-none">
    <div class="hs-overlay-open:opacity-100 hs-overlay-open:duration-500 opacity-0 transition-all sm:max-w-lg sm:w-full m-3 sm:mx-auto min-h-[calc(100%-56px)] flex items-center">
        <div class="pointer-events-auto bg-white border border-border-color rounded-md w-full">
            <form method="POST" action="{{ route('app.hr.departments.store') }}">
                @csrf
                @include('hr::departments._form-fields', ['departments' => $departments, 'employees' => $employees, 'department' => null])
                <div class="flex items-center justify-end gap-2 p-4 border-t border-border-color">
                    <button type="button" data-hs-overlay="#add-department-modal" class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover">{{ __('Save') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

@foreach ($departments as $department)
    <div id="edit-department-modal-{{ $department->id }}" class="hs-overlay hidden fixed inset-0 z-50 overflow-x-hidden overflow-y-auto pointer-events-none">
        <div class="hs-overlay-open:opacity-100 hs-overlay-open:duration-500 opacity-0 transition-all sm:max-w-lg sm:w-full m-3 sm:mx-auto min-h-[calc(100%-56px)] flex items-center">
            <div class="pointer-events-auto bg-white border border-border-color rounded-md w-full">
                <form method="POST" action="{{ route('app.hr.departments.update', $department) }}">
                    @csrf
                    @method('PATCH')
                    @include('hr::departments._form-fields', ['departments' => $departments->reject(fn ($d) => $d->id === $department->id), 'employees' => $employees, 'department' => $department])
                    <div class="flex items-center justify-end gap-2 p-4 border-t border-border-color">
                        <button type="button" data-hs-overlay="#edit-department-modal-{{ $department->id }}" class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light">{{ __('Cancel') }}</button>
                        <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover">{{ __('Update') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endforeach
@endsection
