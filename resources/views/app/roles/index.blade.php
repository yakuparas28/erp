@extends('app.layouts.app')

@section('title', __('Roles & Permissions'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <h1 class="text-gray-900 text-xl font-bold mb-0">{{ __('Roles & Permissions') }}</h1>
    <button type="button" data-hs-overlay="#add-role-modal"
            class="btn-sm bg-dark text-white border border-dark inline-flex items-center gap-2 hover:bg-primary-hover hover:border-primary-hover cursor-pointer">
        <i class="ph ph-plus"></i> {{ __('New Role') }}
    </button>
</div>

<div class="bg-white border border-border-color rounded-md">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-sm text-default border-b border-border-color">
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Role') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Type') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Users') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Created') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($roles as $role)
                    @php($isSystem = $role->tenant_id === null)
                    <tr class="border-b border-border-color">
                        <td class="py-2.5 px-3 text-sm font-semibold text-title">{{ $role->name }}</td>
                        <td class="py-2.5 px-3">
                            <span class="text-[11px] {{ $isSystem ? 'bg-info-transparent text-info' : 'bg-success-transparent text-success' }} px-2 py-0.5 rounded">
                                {{ $isSystem ? __('System') : __('Custom') }}
                            </span>
                        </td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $role->users_count }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $role->created_at?->format('d.m.Y') }}</td>
                        <td class="py-2.5 px-3">
                            <div class="hs-dropdown [--placement:bottom-right] [--auto-close:inside] relative inline-flex">
                                <button type="button" class="hs-dropdown-toggle cursor-pointer btn-sm size-7 bg-white border border-border-color text-gray-600 inline-flex items-center justify-center hover:bg-light focus:outline-hidden" aria-label="{{ __('Actions') }}">
                                    <i class="icon-ellipsis-vertical font-normal"></i>
                                </button>
                                <div class="hs-dropdown-menu transition-[opacity,margin] duration hs-dropdown-open:opacity-100 opacity-0 hidden min-w-50 bg-white border border-border-color shadow rounded-md mt-2 z-10" role="menu">
                                    <div class="p-2 space-y-1">
                                        @unless ($isSystem)
                                            <button type="button" data-hs-overlay="#edit-role-modal-{{ $role->id }}" class="w-full text-start flex items-center gap-2 px-2 py-1.5 rounded-md text-sm text-gray-900 hover:bg-light cursor-pointer">
                                                <i class="icon-pencil-line"></i> {{ __('Edit') }}
                                            </button>
                                        @endunless
                                        <button type="button" data-hs-overlay="#perm-role-modal-{{ $role->id }}" class="w-full text-start flex items-center gap-2 px-2 py-1.5 rounded-md text-sm text-gray-900 hover:bg-light cursor-pointer">
                                            <i class="ph ph-shield-check"></i> {{ __('Permissions') }}
                                        </button>
                                        @unless ($isSystem)
                                            <form method="POST" action="{{ route('app.roles.destroy', $role->id) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="w-full text-start flex items-center gap-2 px-2 py-1.5 rounded-md text-sm text-danger hover:bg-light cursor-pointer">
                                                    <i class="icon-trash-2"></i> {{ __('Delete') }}
                                                </button>
                                            </form>
                                        @endunless
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- Yeni rol modal --}}
<div id="add-role-modal" class="hs-overlay hidden size-full fixed top-0 start-0 z-[80] overflow-x-hidden overflow-y-auto pointer-events-none">
    <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-md sm:w-full m-3 sm:mx-auto pointer-events-auto min-h-screen flex items-center justify-center">
        <form method="POST" action="{{ route('app.roles.store') }}" class="flex flex-col bg-white border shadow-sm rounded-md border-border-color w-full">
            @csrf
            <div class="flex justify-between items-center p-4 border-b border-border-color">
                <h2 class="text-base font-bold text-title">{{ __('New Role') }}</h2>
                <button type="button" class="size-7 inline-flex justify-center items-center rounded-md border border-border-color hover:bg-light cursor-pointer" data-hs-overlay="#add-role-modal" aria-label="{{ __('Cancel') }}">
                    <i class="ph ph-x text-sm"></i>
                </button>
            </div>
            <div class="p-4">
                <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Role') }} <span class="text-danger">*</span></label>
                <input type="text" name="name" required value="{{ old('name') }}"
                       class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
            </div>
            <div class="flex justify-end gap-2 p-4 border-t border-border-color">
                <button type="button" class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light cursor-pointer" data-hs-overlay="#add-role-modal">{{ __('Cancel') }}</button>
                <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Save') }}</button>
            </div>
        </form>
    </div>
</div>

@foreach ($roles as $role)
    @php($isSystem = $role->tenant_id === null)

    {{-- Rol düzenleme modalı --}}
    @unless ($isSystem)
        <div id="edit-role-modal-{{ $role->id }}" class="hs-overlay hidden size-full fixed top-0 start-0 z-[80] overflow-x-hidden overflow-y-auto pointer-events-none">
            <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-md sm:w-full m-3 sm:mx-auto pointer-events-auto min-h-screen flex items-center justify-center">
                <form method="POST" action="{{ route('app.roles.update', $role->id) }}" class="flex flex-col bg-white border shadow-sm rounded-md border-border-color w-full">
                    @csrf
                    @method('PUT')
                    <div class="flex justify-between items-center p-4 border-b border-border-color">
                        <h2 class="text-base font-bold text-title">{{ __('Edit Role') }}</h2>
                        <button type="button" class="size-7 inline-flex justify-center items-center rounded-md border border-border-color hover:bg-light cursor-pointer" data-hs-overlay="#edit-role-modal-{{ $role->id }}" aria-label="{{ __('Cancel') }}">
                            <i class="ph ph-x text-sm"></i>
                        </button>
                    </div>
                    <div class="p-4">
                        <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Role') }} <span class="text-danger">*</span></label>
                        <input type="text" name="name" required value="{{ $role->name }}"
                               class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                    </div>
                    <div class="flex justify-end gap-2 p-4 border-t border-border-color">
                        <button type="button" class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light cursor-pointer" data-hs-overlay="#edit-role-modal-{{ $role->id }}">{{ __('Cancel') }}</button>
                        <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Update') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endunless

    {{-- İzin matrisi modalı (roles-permissions.html kalıbı) --}}
    <div id="perm-role-modal-{{ $role->id }}" class="hs-overlay hidden size-full fixed top-0 start-0 z-[80] overflow-x-hidden overflow-y-auto pointer-events-none">
        <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-xl sm:w-full m-3 sm:mx-auto pointer-events-auto min-h-screen flex items-center justify-center">
            <form method="POST" action="{{ route('app.roles.permissions.update', $role->id) }}" class="flex flex-col bg-white border shadow-sm rounded-md border-border-color w-full">
                @csrf
                @method('PUT')
                <div class="flex justify-between items-center p-4 border-b border-border-color">
                    <h2 class="text-base font-bold text-title">{{ __('Permissions') }} — {{ $role->name }}</h2>
                    <button type="button" class="size-7 inline-flex justify-center items-center rounded-md border border-border-color hover:bg-light cursor-pointer" data-hs-overlay="#perm-role-modal-{{ $role->id }}" aria-label="{{ __('Cancel') }}">
                        <i class="ph ph-x text-sm"></i>
                    </button>
                </div>
                <div class="p-4 max-h-[60vh] overflow-y-auto">
                    @if ($isSystem)
                        <p class="text-[12px] bg-info-transparent text-info rounded-md px-3 py-2 mb-3">{{ __('System role permissions are managed by the platform and cannot be changed.') }}</p>
                    @endif
                    @foreach ($permissionGroups as $group => $permissions)
                        <h3 class="text-sm font-bold text-title mb-2 {{ $loop->first ? '' : 'mt-4' }}">{{ __('permission-group.'.$group) }}</h3>
                        <table class="w-full text-sm border border-border-color rounded-md">
                            <thead>
                                <tr class="text-sm text-default border-b border-border-color">
                                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Permission') }}</th>
                                    <th class="text-right py-2 px-3 font-semibold text-gray-900 w-20">{{ __('Allow') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($permissions as $permission)
                                    <tr class="border-b border-border-color last:border-0">
                                        <td class="py-2 px-3 text-sm text-default">{{ __('permission.'.$permission) }}</td>
                                        <td class="py-2 px-3 text-right">
                                            <input type="checkbox" name="permissions[]" value="{{ $permission }}"
                                                   class="size-4 rounded border-border-color"
                                                   @checked(in_array($permission, $rolePermissions[$role->id] ?? [], true))
                                                   @disabled($isSystem)>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endforeach
                </div>
                <div class="flex justify-end gap-2 p-4 border-t border-border-color">
                    <button type="button" class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light cursor-pointer" data-hs-overlay="#perm-role-modal-{{ $role->id }}">{{ __('Cancel') }}</button>
                    @unless ($isSystem)
                        <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Save') }}</button>
                    @endunless
                </div>
            </form>
        </div>
    </div>
@endforeach
@endsection
