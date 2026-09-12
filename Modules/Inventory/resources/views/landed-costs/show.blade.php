@extends('app.layouts.app')

@section('title', 'LC-' . $landedCost->id)

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <div>
        <h1 class="text-gray-900 text-xl font-bold mb-1">{{ __('Landed Cost') }} LC-{{ $landedCost->id }}</h1>
        <div class="flex items-center gap-2">
            <span class="text-[11px] {{ ['draft'=>'bg-default-transparent text-default','validated'=>'bg-success-transparent text-success','cancelled'=>'bg-danger-transparent text-danger'][$landedCost->status] }} px-2 py-0.5 rounded">
                {{ __('landed-cost-status.'.$landedCost->status) }}
            </span>
            <span class="text-sm text-default">{{ __('Split Method') }}: <strong>{{ __('split-method.'.$landedCost->split_method) }}</strong></span>
            <span class="text-sm text-default">|</span>
            <span class="text-sm text-default">{{ __('Total') }}: <strong>{{ $total }}</strong></span>
        </div>
    </div>
    <a href="{{ route('app.inventory.landed-costs.index') }}" class="btn-sm bg-white border border-border-color text-gray-900 inline-flex items-center gap-2 hover:bg-light">
        <i class="ph ph-arrow-left"></i> {{ __('Back to List') }}
    </a>
</div>

@if (session('status'))
    <div class="bg-success-transparent text-success border border-success rounded-md px-4 py-3 text-sm mb-4">{{ session('status') }}</div>
@endif
@error('landed_cost')<div class="bg-danger-transparent text-danger border border-danger rounded-md px-4 py-3 text-sm mb-4">{{ $message }}</div>@enderror

<div class="grid grid-cols-12 gap-4">
    <div class="col-span-12 lg:col-span-6">
        <div class="bg-white border border-border-color rounded-md">
            <div class="p-4 border-b border-border-color">
                <h2 class="text-base font-bold text-title mb-0">{{ __('Cost Lines') }}</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-sm text-default border-b border-border-color">
                            <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Description') }}</th>
                            <th class="text-right py-2 px-3 font-semibold text-gray-900">{{ __('Amount') }}</th>
                            @if ($landedCost->status === 'draft')
                                <th class="text-right py-2 px-3 font-semibold text-gray-900"></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($landedCost->lines as $line)
                            <tr class="border-b border-border-color">
                                <td class="py-2 px-3 text-sm text-title">{{ $line->description }}</td>
                                <td class="py-2 px-3 text-sm text-default text-right">{{ $line->amount }}</td>
                                @if ($landedCost->status === 'draft')
                                    <td class="py-2 px-3 text-right">
                                        <form method="POST" action="{{ route('app.inventory.landed-costs.lines.destroy', $line) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-danger text-xs hover:underline">{{ __('Remove') }}</button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="3" class="py-6 text-center text-sm text-default">{{ __('No cost lines.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($landedCost->status === 'draft')
                <div class="p-4 border-t border-border-color">
                    <form method="POST" action="{{ route('app.inventory.landed-costs.lines.store', $landedCost) }}" class="flex flex-wrap items-end gap-2">
                        @csrf
                        <div class="flex-1 min-w-40">
                            <label class="text-xs text-default mb-1 block">{{ __('Description') }}</label>
                            <input type="text" name="description" required placeholder="{{ __('e.g. Kargo, Gümrük') }}" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                        </div>
                        <div class="w-32">
                            <label class="text-xs text-default mb-1 block">{{ __('Amount') }}</label>
                            <input type="number" name="amount" required step="0.0001" min="0.0001" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                        </div>
                        <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Add') }}</button>
                    </form>
                </div>
            @endif
        </div>
    </div>

    <div class="col-span-12 lg:col-span-6">
        @if ($landedCost->status === 'validated')
            <div class="bg-white border border-border-color rounded-md">
                <div class="p-4 border-b border-border-color">
                    <h2 class="text-base font-bold text-title mb-0">{{ __('Distributions') }}</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-sm text-default border-b border-border-color">
                                <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Move') }}</th>
                                <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Product') }}</th>
                                <th class="text-right py-2 px-3 font-semibold text-gray-900">{{ __('Allocated Amount') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($landedCost->distributions as $d)
                                <tr class="border-b border-border-color">
                                    <td class="py-2 px-3 text-xs font-mono text-default">SM-{{ $d->stock_move_id }}</td>
                                    <td class="py-2 px-3 text-sm text-title">{{ $d->stockMove->product->name }}</td>
                                    <td class="py-2 px-3 text-sm font-semibold text-right">{{ $d->allocated_amount }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @elseif ($landedCost->status === 'draft')
            <div class="bg-white border border-border-color rounded-md">
                <div class="p-4 border-b border-border-color">
                    <h2 class="text-base font-bold text-title mb-1">{{ __('Select Purchase Moves') }}</h2>
                    <p class="text-xs text-default mb-0">{{ __('Costs will be distributed across the selected receipt moves.') }}</p>
                </div>
                <form method="POST" action="{{ route('app.inventory.landed-costs.validate', $landedCost) }}">
                    @csrf
                    <div class="overflow-x-auto max-h-96 overflow-y-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-sm text-default border-b border-border-color sticky top-0 bg-white">
                                    <th class="py-2 px-3 w-8"></th>
                                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Date') }}</th>
                                    <th class="text-left py-2 px-3 font-semibold text-gray-900">{{ __('Product') }}</th>
                                    <th class="text-right py-2 px-3 font-semibold text-gray-900">{{ __('Qty') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($eligibleMoves as $move)
                                    <tr class="border-b border-border-color">
                                        <td class="py-2 px-3">
                                            <input type="checkbox" name="stock_move_ids[]" value="{{ $move->id }}" class="rounded border-border-color">
                                        </td>
                                        <td class="py-2 px-3 text-xs text-default">{{ $move->created_at->format('d.m.Y') }}</td>
                                        <td class="py-2 px-3 text-sm text-title">{{ $move->product->name }}</td>
                                        <td class="py-2 px-3 text-sm text-default text-right">{{ $move->qty }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="py-6 text-center text-sm text-default">{{ __('No eligible receipt moves.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if ($landedCost->lines->isNotEmpty() && $eligibleMoves->isNotEmpty())
                        <div class="p-4 border-t border-border-color">
                            <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer w-full">
                                <i class="ph ph-check"></i> {{ __('Validate & Distribute') }}
                            </button>
                        </div>
                    @endif
                </form>
            </div>
        @endif
    </div>
</div>
@endsection
