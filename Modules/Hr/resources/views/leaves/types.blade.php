@extends('app.layouts.app')

@section('title', __('Leave Types'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <h1 class="text-gray-900 text-xl font-bold mb-0">{{ __('Leave Types') }}</h1>
    <button type="button" data-hs-overlay="#add-type-modal" class="btn-sm bg-dark text-white border border-dark inline-flex items-center gap-2 hover:bg-primary-hover">
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
    <div class="overflow-x-auto -mx-4">
        <table class="w-full text-xs">
            <thead>
                <tr class="border-y border-border-color bg-light text-sm">
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Code') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Leave Type') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Unit') }}</th>
                    <th class="text-right py-2 px-3 font-semibold text-gray-900">{{ __('Max Days/Year') }}</th>
                    <th class="text-center py-2 px-3 font-semibold text-gray-900">{{ __('Deducts') }}</th>
                    <th class="text-center py-2 px-3 font-semibold text-gray-900">{{ __('Document Req.') }}</th>
                    <th class="text-center py-2 px-3 font-semibold text-gray-900">{{ __('2nd Level') }}</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Status') }}</th>
                    <th class="text-center py-2 px-3 font-semibold text-gray-900">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($types as $t)
                    <tr class="border-b border-border-color hover:bg-light/50">
                        <td class="py-3 px-3 font-mono text-primary">LT-{{ str_pad((string) $t->id, 3, '0', STR_PAD_LEFT) }}</td>
                        <td class="py-3 px-3">
                            <div class="font-semibold text-title text-sm">{{ $t->name }}</div>
                            <div class="font-mono text-[10px] text-default">{{ $t->key }}</div>
                        </td>
                        <td class="py-3 px-3 text-sm">{{ __($t->unit) }}</td>
                        <td class="py-3 px-3 text-right text-sm">{{ $t->max_days_per_year ?? '—' }}</td>
                        <td class="py-3 px-3 text-center">{{ $t->deducts_from_balance ? '✓' : '—' }}</td>
                        <td class="py-3 px-3 text-center">{{ $t->requires_document ? '✓' : '—' }}</td>
                        <td class="py-3 px-3 text-center">{{ $t->requires_second_level ? '✓' : '—' }}</td>
                        <td class="py-3 px-3">
                            @if ($t->is_active)
                                <span class="text-[11px] bg-success-transparent text-success px-2 py-0.5 rounded">{{ __('Active') }}</span>
                            @else
                                <span class="text-[11px] bg-danger-transparent text-danger px-2 py-0.5 rounded">{{ __('Inactive') }}</span>
                            @endif
                        </td>
                        <td class="py-3 px-3">
                            <div class="flex items-center justify-center">
                                <form method="POST" action="{{ route('app.hr.leave-types.destroy', $t) }}" onsubmit="return confirm('{{ __('Delete this leave type?') }}')">
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
                    <tr><td colspan="9" class="py-8 text-center text-sm text-default">{{ __('No leave types.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div id="add-type-modal" class="hs-overlay hidden fixed inset-0 z-50 overflow-x-hidden overflow-y-auto pointer-events-none">
    <div class="hs-overlay-open:opacity-100 opacity-0 transition-all sm:max-w-lg sm:w-full m-3 sm:mx-auto min-h-[calc(100%-56px)] flex items-center">
        <div class="pointer-events-auto bg-white border border-border-color rounded-md w-full shadow">
            <form method="POST" action="{{ route('app.hr.leave-types.store') }}">
                @csrf
                <div class="p-4 border-b border-border-color flex items-center justify-between">
                    <h3 class="text-base font-bold text-title mb-0">{{ __('New Leave Type') }}</h3>
                    <button type="button" data-hs-overlay="#add-type-modal" class="size-7 rounded-md border border-border-color flex items-center justify-center hover:bg-light"><i class="ph ph-x"></i></button>
                </div>
                <div class="p-4 grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-semibold text-gray-900 mb-1">{{ __('Key') }} <span class="text-danger">*</span></label>
                        <input type="text" name="key" class="form-control" required maxlength="40" placeholder="yillik">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-900 mb-1">{{ __('Name') }} <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required maxlength="100" placeholder="Yıllık İzin">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-900 mb-1">{{ __('Unit') }} <span class="text-danger">*</span></label>
                        <select name="unit" class="form-control" required>
                            <option value="day">{{ __('day') }}</option>
                            <option value="half_day">{{ __('half_day') }}</option>
                            <option value="hour">{{ __('hour') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-900 mb-1">{{ __('Max Days/Year') }}</label>
                        <input type="number" name="max_days_per_year" min="0" class="form-control">
                    </div>
                    <div class="col-span-2 space-y-2 text-sm">
                        <label class="flex items-center gap-2"><input type="hidden" name="deducts_from_balance" value="0"><input type="checkbox" name="deducts_from_balance" value="1" checked> {{ __('Deducts from balance') }}</label>
                        <label class="flex items-center gap-2"><input type="hidden" name="requires_document" value="0"><input type="checkbox" name="requires_document" value="1"> {{ __('Requires document') }}</label>
                        <label class="flex items-center gap-2"><input type="hidden" name="requires_second_level" value="0"><input type="checkbox" name="requires_second_level" value="1"> {{ __('Requires second-level approval') }}</label>
                        <label class="flex items-center gap-2"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" checked> {{ __('Active') }}</label>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-2 p-4 border-t border-border-color">
                    <button type="button" data-hs-overlay="#add-type-modal" class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover">{{ __('Save') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
