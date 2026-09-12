@extends('app.layouts.app')

@section('title', $batch->name)

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <div>
        <h1 class="text-gray-900 text-xl font-bold mb-1">{{ $batch->name }}</h1>
        <span class="text-[11px] {{ ['draft'=>'bg-default-transparent text-default','in_progress'=>'bg-warning-transparent text-warning','done'=>'bg-success-transparent text-success','cancelled'=>'bg-danger-transparent text-danger'][$batch->status] }} px-2 py-0.5 rounded">
            {{ __('batch-status.'.$batch->status) }}
        </span>
        @if ($batch->doneBy)
            <span class="text-xs text-default ms-2">{{ __('Done by') }}: {{ $batch->doneBy->name }} · {{ $batch->updated_at->format('d.m.Y H:i') }}</span>
        @endif
    </div>
    <a href="{{ route('app.inventory.transfer-batches.index') }}" class="btn-sm bg-white border border-border-color text-gray-900 inline-flex items-center gap-2 hover:bg-light">
        <i class="ph ph-arrow-left"></i> {{ __('Back to List') }}
    </a>
</div>

@if (session('status'))
    <div class="bg-success-transparent text-success border border-success rounded-md px-4 py-3 text-sm mb-4">{{ session('status') }}</div>
@endif
@error('batch')<div class="bg-danger-transparent text-danger border border-danger rounded-md px-4 py-3 text-sm mb-4">{{ $message }}</div>@enderror

<div class="grid grid-cols-12 gap-4">
    <div class="col-span-12 lg:col-span-8">
        <div class="bg-white border border-border-color rounded-md">
            <div class="p-4 border-b border-border-color">
                <h2 class="text-base font-bold text-title mb-0">{{ __('Batched Transfers') }}</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-sm text-default border-b border-border-color">
                            <th class="text-left py-2 px-3 font-semibold text-gray-900">#</th>
                            <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('From') }}</th>
                            <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('To') }}</th>
                            <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Status') }}</th>
                            @if (in_array($batch->status, ['draft', 'in_progress']))
                                <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Actions') }}</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($batch->transfers as $transfer)
                            <tr class="border-b border-border-color">
                                <td class="py-2.5 px-3 text-sm font-semibold text-title">#{{ $transfer->id }}</td>
                                <td class="py-2.5 px-3 text-sm text-default">{{ $transfer->fromLocation->name }}</td>
                                <td class="py-2.5 px-3 text-sm text-default">{{ $transfer->toLocation->name }}</td>
                                <td class="py-2.5 px-3 text-xs text-default">{{ $transfer->status }}</td>
                                @if (in_array($batch->status, ['draft', 'in_progress']))
                                    <td class="py-2.5 px-3">
                                        @if ($transfer->status === 'draft')
                                            <form method="POST" action="{{ route('app.inventory.transfer-batches.transfers.remove', $transfer) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-danger text-xs hover:underline">{{ __('Remove') }}</button>
                                            </form>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-6 text-center text-sm text-default">{{ __('No transfers in this batch yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($batch->status === 'draft' && $availableTransfers->isNotEmpty())
            <div class="bg-white border border-border-color rounded-md p-4 mt-4">
                <h3 class="text-sm font-semibold text-title mb-2">{{ __('Add draft transfer') }}</h3>
                <form method="POST" action="{{ route('app.inventory.transfer-batches.transfers.add', $batch) }}" class="flex flex-wrap items-end gap-2">
                    @csrf
                    <div class="flex-1 min-w-40">
                        <select name="transfer_id" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                            @foreach ($availableTransfers as $t)
                                <option value="{{ $t->id }}">#{{ $t->id }} · {{ $t->fromLocation->name }} → {{ $t->toLocation->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Add') }}</button>
                </form>
            </div>
        @endif
    </div>

    <div class="col-span-12 lg:col-span-4">
        <div class="bg-white border border-border-color rounded-md p-4">
            <h2 class="text-base font-bold text-title mb-3">{{ __('Actions') }}</h2>
            @if (in_array($batch->status, ['draft', 'in_progress']))
                <form method="POST" action="{{ route('app.inventory.transfer-batches.complete', $batch) }}" class="mb-2">
                    @csrf
                    <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer w-full">
                        <i class="ph ph-check"></i> {{ __('Complete Batch') }}
                    </button>
                </form>
                <form method="POST" action="{{ route('app.inventory.transfer-batches.cancel', $batch) }}" onsubmit="return confirm('{{ __('Cancel this batch?') }}')">
                    @csrf
                    <button type="submit" class="btn-sm bg-white border border-danger text-danger hover:bg-danger hover:text-white cursor-pointer w-full">
                        <i class="ph ph-x"></i> {{ __('Cancel Batch') }}
                    </button>
                </form>
            @else
                <p class="text-sm text-default mb-0">{{ __('Batch is finalized.') }}</p>
            @endif
        </div>
    </div>
</div>
@endsection
