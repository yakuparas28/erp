@extends('app.layouts.app')

@section('title', __('Pending Approvals'))

@section('content')
<h1 class="text-gray-900 text-xl font-bold mb-4">{{ __('Pending Approvals') }}</h1>

@if (session('success'))
    <div class="bg-success-transparent text-success border border-success rounded-md px-4 py-3 text-sm mb-4">{{ session('success') }}</div>
@endif
@if ($errors->any())
    <div class="bg-danger-transparent text-danger border border-danger rounded-md px-4 py-3 text-sm mb-4">
        @foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
@endif

<div class="bg-white border border-border-color rounded-md overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-border-color text-left">
            <tr>
                <th class="p-3">#</th>
                <th class="p-3">{{ __('Requester') }}</th>
                <th class="p-3">{{ __('Vehicle') }}</th>
                <th class="p-3">{{ __('Project') }}</th>
                <th class="p-3">{{ __('Additional Drivers') }}</th>
                <th class="p-3">{{ __('Pickup Datetime') }}</th>
                <th class="p-3">{{ __('Return Datetime') }}</th>
                <th class="p-3 text-right">{{ __('Actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($pending as $r)
                <tr class="border-b border-border-color">
                    <td class="p-3 font-mono">#{{ str_pad((string) $r->id, 5, '0', STR_PAD_LEFT) }}</td>
                    <td class="p-3">
                        <div class="font-semibold">{{ $r->aktifSofor?->name }}</div>
                        <div class="text-xs text-default">{{ $r->aktifSofor?->email }}</div>
                    </td>
                    <td class="p-3">{{ $r->vehicle?->plaka }} — {{ $r->vehicle?->marka_model }}</td>
                    <td class="p-3">{{ $r->project?->ad ?? '—' }}</td>
                    <td class="p-3">
                        @foreach ($r->ekSoforler as $s)
                            <span class="inline-block px-2 py-0.5 text-xs bg-gray-100 rounded mr-1">{{ $s->name }}</span>
                        @endforeach
                    </td>
                    <td class="p-3 text-success">{{ $r->planlanan_alis_at->format('d.m.Y H:i') }}</td>
                    <td class="p-3 text-warning">{{ $r->planlanan_teslim_at->format('d.m.Y H:i') }}</td>
                    <td class="p-3 text-right whitespace-nowrap">
                        <form method="POST" action="{{ route('app.fleet.approvals.approve', $r) }}" class="inline">
                            @csrf
                            <button class="btn-sm bg-success text-white">{{ __('Approve') }}</button>
                        </form>
                        <button type="button" class="btn-sm bg-danger text-white" onclick="document.getElementById('reject-form-{{ $r->id }}').action='{{ route('app.fleet.approvals.reject', $r) }}'" data-hs-overlay="#reject-{{ $r->id }}">{{ __('Reject') }}</button>

                        <div id="reject-{{ $r->id }}" class="hs-overlay hidden fixed top-0 start-0 w-full h-full z-[70] overflow-x-hidden overflow-y-auto pointer-events-none">
                            <div class="opacity-0 transition-all sm:max-w-lg sm:w-full m-3 sm:mx-auto flex items-center min-h-[calc(100%-56px)]">
                                <div class="w-full bg-white border rounded-xl pointer-events-auto">
                                    <div class="flex justify-between items-center py-3 px-4 border-b"><h3 class="font-bold">{{ __('Reject') }}</h3><button type="button" data-hs-overlay="#reject-{{ $r->id }}"><i class="ph ph-x"></i></button></div>
                                    <form id="reject-form-{{ $r->id }}" method="POST" action="{{ route('app.fleet.approvals.reject', $r) }}">
                                        @csrf
                                        <div class="p-4">
                                            <label class="text-xs text-default">{{ __('Rejection reason') }}</label>
                                            <textarea name="red_aciklamasi" rows="4" required minlength="5" maxlength="500" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white"></textarea>
                                        </div>
                                        <div class="flex justify-end gap-2 py-3 px-4 border-t">
                                            <button type="button" class="btn-sm border border-border-color" data-hs-overlay="#reject-{{ $r->id }}">{{ __('Cancel') }}</button>
                                            <button class="btn-sm bg-danger text-white">{{ __('Reject') }}</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="p-6 text-center text-default">{{ __('No pending approvals.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $pending->links() }}</div>
@endsection
