@extends('app.layouts.app')

@section('title', __('Approval Workflows'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <div>
        <h1 class="text-gray-900 text-xl font-bold mb-1">{{ __('Approval Workflows') }}</h1>
        <p class="text-sm text-default mb-0">{{ __('Configure multi-step approval flows per subject type (leave request, vehicle reservation, ...). Each step can target a manager, department manager, role or specific user.') }}</p>
    </div>
    <button type="button" data-hs-overlay="#add-wf-modal" class="btn-sm bg-dark text-white border border-dark inline-flex items-center gap-2 hover:bg-primary-hover cursor-pointer">
        <i class="ph ph-plus"></i> {{ __('New Workflow') }}
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
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Subject Type') }}</th>
                    <th class="text-right py-2 px-3 font-semibold text-gray-900">{{ __('Steps') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Status') }}</th>
                    <th class="text-right py-2 px-3 font-semibold text-gray-900">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($workflows as $wf)
                    <tr class="border-b border-border-color">
                        <td class="py-2.5 px-3">
                            <a href="{{ route('app.approval-workflows.show', $wf) }}" class="font-semibold text-title hover:text-primary">{{ $wf->name }}</a>
                        </td>
                        <td class="py-2.5 px-3">
                            <span class="text-[11px] bg-primary-transparent text-primary px-2 py-0.5 rounded font-mono">{{ $subjectTypes[$wf->subject_type] ?? $wf->subject_type }}</span>
                        </td>
                        <td class="py-2.5 px-3 text-right font-semibold">{{ $wf->steps_count }}</td>
                        <td class="py-2.5 px-3">
                            @if ($wf->is_active)
                                <span class="text-[11px] bg-success-transparent text-success px-2 py-0.5 rounded">{{ __('Active') }}</span>
                            @else
                                <span class="text-[11px] bg-danger-transparent text-danger px-2 py-0.5 rounded">{{ __('Inactive') }}</span>
                            @endif
                        </td>
                        <td class="py-2.5 px-3 text-right">
                            <div class="flex items-center justify-end gap-1">
                                <a href="{{ route('app.approval-workflows.show', $wf) }}" class="size-7 rounded-md border border-border-color inline-flex items-center justify-center text-gray-900 hover:bg-light" title="{{ __('View') }}">
                                    <i class="ph ph-eye"></i>
                                </a>
                                <form method="POST" action="{{ route('app.approval-workflows.destroy', $wf) }}" onsubmit="return confirm('{{ __('Delete this workflow?') }}')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="size-7 rounded-md border border-border-color inline-flex items-center justify-center text-danger hover:bg-light" title="{{ __('Delete') }}">
                                        <i class="ph ph-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-sm text-default">{{ __('No approval workflows yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div id="add-wf-modal" class="hs-overlay hidden size-full fixed top-0 start-0 z-[80] overflow-x-hidden overflow-y-auto pointer-events-none">
    <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-lg sm:w-full m-3 sm:mx-auto pointer-events-auto min-h-screen flex items-center justify-center">
        <form method="POST" action="{{ route('app.approval-workflows.store') }}" class="flex flex-col bg-white border shadow-sm rounded-md border-border-color w-full">
            @csrf
            <div class="flex justify-between items-center p-4 border-b border-border-color">
                <h2 class="text-base font-bold text-title">{{ __('New Workflow') }}</h2>
                <button type="button" class="size-7 inline-flex justify-center items-center rounded-md border border-border-color hover:bg-light cursor-pointer" data-hs-overlay="#add-wf-modal"><i class="ph ph-x text-sm"></i></button>
            </div>
            <div class="p-4 grid grid-cols-12 gap-3">
                <div class="col-span-12">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Name') }} <span class="text-danger">*</span></label>
                    <input type="text" name="name" required maxlength="100" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0" placeholder="İzin Onay Akışı">
                </div>
                <div class="col-span-12">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Subject Type') }} <span class="text-danger">*</span></label>
                    <select name="subject_type" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                        @foreach ($subjectTypes as $key => $label)
                            <option value="{{ $key }}">{{ $label }} ({{ $key }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-span-12">
                    <label class="flex items-center gap-2 text-sm text-gray-900">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" checked>
                        {{ __('Active') }}
                    </label>
                </div>
            </div>
            <div class="flex justify-end gap-2 p-4 border-t border-border-color">
                <button type="button" class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light cursor-pointer" data-hs-overlay="#add-wf-modal">{{ __('Cancel') }}</button>
                <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Save') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
