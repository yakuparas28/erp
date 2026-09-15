@extends('app.layouts.app')

@section('title', __('Expense Approvals'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <div>
        <h1 class="text-gray-900 text-xl max-lg:text-lg font-bold mb-0">{{ __('Expense Approvals') }}</h1>
        <p class="text-[12px] text-default mb-0 mt-1">{{ __('Approve, refuse or post submitted expenses.') }}</p>
    </div>
</div>

@if (session('status'))<div class="bg-success-transparent text-success border border-success rounded-md px-4 py-3 text-sm mb-4">{{ session('status') }}</div>@endif
@if ($errors->any())<div class="bg-danger-transparent text-danger border border-danger rounded-md px-4 py-3 text-sm mb-4">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif

<div class="bg-white border border-border-color rounded-md p-4 mb-4">
    <h2 class="text-base font-bold text-title mb-3 inline-flex items-center gap-2"><i class="ph ph-hourglass text-warning"></i> {{ __('Waiting Approval') }} <span class="text-xs text-default">({{ $pending->count() }})</span></h2>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-sm text-default border-b border-border-color">
                    <th class="text-left py-2 px-2 font-semibold text-gray-900">{{ __('Date') }}</th>
                    <th class="text-left py-2 px-2 font-semibold text-gray-900">{{ __('Employee') }}</th>
                    <th class="text-left py-2 px-2 font-semibold text-gray-900">{{ __('Category') }}</th>
                    <th class="text-left py-2 px-2 font-semibold text-gray-900">{{ __('Description') }}</th>
                    <th class="text-right py-2 px-2 font-semibold text-gray-900">{{ __('Amount') }}</th>
                    <th class="text-left py-2 px-2 font-semibold text-gray-900">{{ __('Paid By') }}</th>
                    <th class="text-right py-2 px-2 font-semibold text-gray-900">{{ __('Action') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($pending as $e)
                    <tr class="border-b border-border-color">
                        <td class="py-2.5 px-2 text-sm text-default">{{ $e->expense_date->translatedFormat('d M Y') }}</td>
                        <td class="py-2.5 px-2 text-sm font-semibold text-title">{{ $e->employee?->full_name }}</td>
                        <td class="py-2.5 px-2 text-sm text-default">{{ $e->category?->name }}</td>
                        <td class="py-2.5 px-2 text-sm text-default">{{ $e->description }}</td>
                        <td class="py-2.5 px-2 text-sm text-title font-semibold text-right">{{ number_format((float) $e->total_amount, 2) }} <span class="text-[10px] text-default">{{ $e->currency_code }}</span></td>
                        <td class="py-2.5 px-2 text-sm text-default">{{ __('paid-by.'.$e->paid_by) }}</td>
                        <td class="py-2.5 px-2 text-right whitespace-nowrap">
                            @if ($e->receipt_path)
                                <a href="{{ \Storage::url($e->receipt_path) }}" target="_blank" class="size-7 rounded-md border border-border-color inline-flex items-center justify-center text-default hover:bg-light" title="{{ __('Receipt') }}"><i class="ph ph-image"></i></a>
                            @endif
                            <form method="POST" action="{{ route('app.expenses.approvals.approve', $e) }}" class="inline">
                                @csrf
                                <button class="btn-sm bg-success text-white px-2 py-1 text-xs">{{ __('Approve') }}</button>
                            </form>
                            <button type="button" data-hs-overlay="#refuse-{{ $e->id }}" class="btn-sm bg-danger text-white px-2 py-1 text-xs">{{ __('Refuse') }}</button>

                            <div id="refuse-{{ $e->id }}" class="hs-overlay hidden fixed top-0 start-0 w-full h-full z-[80] overflow-x-hidden overflow-y-auto pointer-events-none">
                                <div class="opacity-0 transition-all sm:max-w-lg sm:w-full m-3 sm:mx-auto flex items-center min-h-[calc(100%-56px)]">
                                    <div class="w-full bg-white border rounded-xl pointer-events-auto">
                                        <div class="flex justify-between items-center py-3 px-4 border-b"><h3 class="font-bold">{{ __('Refuse') }}</h3><button type="button" data-hs-overlay="#refuse-{{ $e->id }}"><i class="ph ph-x"></i></button></div>
                                        <form method="POST" action="{{ route('app.expenses.approvals.refuse', $e) }}">
                                            @csrf
                                            <div class="p-4">
                                                <label class="text-xs text-default">{{ __('Refusal reason') }}</label>
                                                <textarea name="refuse_reason" rows="4" required minlength="5" maxlength="1000" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white"></textarea>
                                            </div>
                                            <div class="flex justify-end gap-2 py-3 px-4 border-t">
                                                <button type="button" class="btn-sm border border-border-color" data-hs-overlay="#refuse-{{ $e->id }}">{{ __('Cancel') }}</button>
                                                <button class="btn-sm bg-danger text-white">{{ __('Refuse') }}</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-6 text-center text-sm text-default">{{ __('No pending approvals.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="bg-white border border-border-color rounded-md p-4">
    <h2 class="text-base font-bold text-title mb-3 inline-flex items-center gap-2"><i class="ph ph-check-circle text-info"></i> {{ __('Approved — Ready to Post') }} <span class="text-xs text-default">({{ $approved->count() }})</span></h2>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-sm text-default border-b border-border-color">
                    <th class="text-left py-2 px-2 font-semibold text-gray-900">{{ __('Date') }}</th>
                    <th class="text-left py-2 px-2 font-semibold text-gray-900">{{ __('Employee') }}</th>
                    <th class="text-left py-2 px-2 font-semibold text-gray-900">{{ __('Category') }}</th>
                    <th class="text-right py-2 px-2 font-semibold text-gray-900">{{ __('Amount') }}</th>
                    <th class="text-right py-2 px-2 font-semibold text-gray-900">{{ __('Action') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($approved as $e)
                    <tr class="border-b border-border-color">
                        <td class="py-2.5 px-2 text-sm text-default">{{ $e->expense_date->translatedFormat('d M Y') }}</td>
                        <td class="py-2.5 px-2 text-sm font-semibold text-title">{{ $e->employee?->full_name }}</td>
                        <td class="py-2.5 px-2 text-sm text-default">{{ $e->category?->name }}</td>
                        <td class="py-2.5 px-2 text-sm text-title font-semibold text-right">{{ number_format((float) $e->total_amount, 2) }} <span class="text-[10px] text-default">{{ $e->currency_code }}</span></td>
                        <td class="py-2.5 px-2 text-right">
                            @can('post expense')
                                <form method="POST" action="{{ route('app.expenses.approvals.post', $e) }}" class="inline">
                                    @csrf
                                    <button class="btn-sm bg-dark text-white px-2 py-1 text-xs"><i class="ph ph-receipt"></i> {{ __('Post to Accounting') }}</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-6 text-center text-sm text-default">{{ __('No approved expenses waiting to post.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
