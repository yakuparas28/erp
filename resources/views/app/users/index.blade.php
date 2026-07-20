@extends('app.layouts.app')

@section('title', __('Users'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <h1 class="text-gray-900 text-xl font-bold mb-0">{{ __('Users') }}</h1>
    <button type="button" data-hs-overlay="#invite-user-modal"
            class="btn-sm bg-dark text-white border border-dark inline-flex items-center gap-2 hover:bg-primary-hover hover:border-primary-hover cursor-pointer">
        <i class="ph ph-plus"></i> {{ __('Invite User') }}
    </button>
</div>

<div class="bg-white border border-border-color rounded-md">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-sm text-default border-b border-border-color">
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Name') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Email') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Role') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Created') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($users as $user)
                    <tr class="border-b border-border-color">
                        <td class="py-2.5 px-3 text-sm font-semibold text-title">{{ $user->name }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $user->email }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $user->roles->pluck('name')->join(', ') ?: '—' }}</td>
                        <td class="py-2.5 px-3 text-sm text-default">{{ $user->created_at->format('d.m.Y') }}</td>
                        <td class="py-2.5 px-3">
                            <div class="hs-dropdown [--placement:bottom-right] [--auto-close:inside] relative inline-flex">
                                <button type="button" class="hs-dropdown-toggle cursor-pointer btn-sm size-7 bg-white border border-border-color text-gray-600 inline-flex items-center justify-center hover:bg-light focus:outline-hidden" aria-label="{{ __('Actions') }}">
                                    <i class="icon-ellipsis-vertical font-normal"></i>
                                </button>
                                <div class="hs-dropdown-menu transition-[opacity,margin] duration hs-dropdown-open:opacity-100 opacity-0 hidden min-w-50 bg-white border border-border-color shadow rounded-md mt-2 z-10" role="menu">
                                    <div class="p-2 space-y-1">
                                        <button type="button" data-hs-overlay="#edit-user-modal-{{ $user->id }}" class="w-full text-start flex items-center gap-2 px-2 py-1.5 rounded-md text-sm text-gray-900 hover:bg-light cursor-pointer">
                                            <i class="icon-pencil-line"></i> {{ __('Edit') }}
                                        </button>
                                        @unless ($user->is(auth()->user()))
                                            <form method="POST" action="{{ route('app.users.destroy', $user->id) }}">
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

{{-- Davet modalı --}}
<div id="invite-user-modal" class="hs-overlay hidden size-full fixed top-0 start-0 z-[80] overflow-x-hidden overflow-y-auto pointer-events-none">
    <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-xl sm:w-full m-3 sm:mx-auto pointer-events-auto min-h-screen flex items-center justify-center">
        <form method="POST" action="{{ route('app.users.store') }}" class="flex flex-col bg-white border shadow-sm rounded-md border-border-color w-full">
            @csrf
            <div class="flex justify-between items-center p-4 border-b border-border-color">
                <h2 class="text-base font-bold text-title">{{ __('Invite User') }}</h2>
                <button type="button" class="size-7 inline-flex justify-center items-center rounded-md border border-border-color hover:bg-light cursor-pointer" data-hs-overlay="#invite-user-modal" aria-label="{{ __('Cancel') }}">
                    <i class="ph ph-x text-sm"></i>
                </button>
            </div>
            <div class="p-4 grid grid-cols-12 gap-3">
                <div class="col-span-12 sm:col-span-6">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Name') }} <span class="text-danger">*</span></label>
                    <input type="text" name="name" required value="{{ old('name') }}"
                           class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                </div>
                <div class="col-span-12 sm:col-span-6">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Email') }} <span class="text-danger">*</span></label>
                    <input type="email" name="email" required value="{{ old('email') }}"
                           class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                </div>
                <div class="col-span-12">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Role') }} <span class="text-danger">*</span></label>
                    <div class="space-y-1">
                        @foreach ($assignableRoles as $roleName)
                            <label class="flex items-center gap-2 text-sm text-default">
                                <input type="checkbox" name="roles[]" value="{{ $roleName }}" class="size-4 rounded border-border-color">
                                {{ $roleName }}
                            </label>
                        @endforeach
                    </div>
                    <p class="text-[11px] text-default mt-2 mb-0">{{ __('A temporary password is generated and emailed to the user.') }}</p>
                </div>
            </div>
            <div class="flex justify-end gap-2 p-4 border-t border-border-color">
                <button type="button" class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light cursor-pointer" data-hs-overlay="#invite-user-modal">{{ __('Cancel') }}</button>
                <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Send Invitation') }}</button>
            </div>
        </form>
    </div>
</div>

{{-- Kullanıcı düzenleme modalları --}}
@foreach ($users as $user)
    <div id="edit-user-modal-{{ $user->id }}" class="hs-overlay hidden size-full fixed top-0 start-0 z-[80] overflow-x-hidden overflow-y-auto pointer-events-none">
        <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-xl sm:w-full m-3 sm:mx-auto pointer-events-auto min-h-screen flex items-center justify-center">
            <form method="POST" action="{{ route('app.users.update', $user->id) }}" class="flex flex-col bg-white border shadow-sm rounded-md border-border-color w-full">
                @csrf
                @method('PUT')
                <div class="flex justify-between items-center p-4 border-b border-border-color">
                    <h2 class="text-base font-bold text-title">{{ __('Edit') }} — {{ $user->name }}</h2>
                    <button type="button" class="size-7 inline-flex justify-center items-center rounded-md border border-border-color hover:bg-light cursor-pointer" data-hs-overlay="#edit-user-modal-{{ $user->id }}" aria-label="{{ __('Cancel') }}">
                        <i class="ph ph-x text-sm"></i>
                    </button>
                </div>
                <div class="p-4 grid grid-cols-12 gap-3">
                    <div class="col-span-12">
                        <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Name') }} <span class="text-danger">*</span></label>
                        <input type="text" name="name" required value="{{ $user->name }}"
                               class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                    </div>
                    <div class="col-span-12">
                        <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Role') }} <span class="text-danger">*</span></label>
                        <div class="space-y-1">
                            @foreach ($assignableRoles as $roleName)
                                <label class="flex items-center gap-2 text-sm text-default">
                                    <input type="checkbox" name="roles[]" value="{{ $roleName }}" class="size-4 rounded border-border-color"
                                           @checked($user->roles->pluck('name')->contains($roleName))>
                                    {{ $roleName }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="flex justify-end gap-2 p-4 border-t border-border-color">
                    <button type="button" class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light cursor-pointer" data-hs-overlay="#edit-user-modal-{{ $user->id }}">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Update') }}</button>
                </div>
            </form>
        </div>
    </div>
@endforeach
@endsection
