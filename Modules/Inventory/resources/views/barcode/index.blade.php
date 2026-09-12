@extends('app.layouts.app')

@section('title', __('Barcode Operator'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <div>
        <h1 class="text-gray-900 text-xl font-bold mb-1">{{ __('Barcode Operator') }}</h1>
        <p class="text-sm text-default mb-0">{{ __('Scan a product barcode with a USB/keyboard scanner to record a quick stock move.') }}</p>
    </div>
</div>

@if (session('status'))
    <div class="bg-success-transparent text-success border border-success rounded-md px-4 py-3 text-sm mb-4">{{ session('status') }}</div>
@endif
@error('barcode')<div class="bg-danger-transparent text-danger border border-danger rounded-md px-4 py-3 text-sm mb-4">{{ $message }}</div>@enderror

<div class="grid grid-cols-12 gap-4">
    <div class="col-span-12 lg:col-span-6">
        <div class="bg-white border border-border-color rounded-md p-4">
            <h2 class="text-base font-bold text-title mb-3">{{ __('Quick Move') }}</h2>
            <form method="POST" action="{{ route('app.inventory.barcode.move') }}" id="barcode-form">
                @csrf
                <div class="mb-3">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Barcode') }} <span class="text-danger">*</span></label>
                    <input type="text" id="barcode-input" name="barcode" required autofocus placeholder="{{ __('Scan or type barcode…') }}" class="w-full px-3 py-3 text-lg border-2 border-info rounded-md bg-white focus:outline-none focus:ring-0 font-mono">
                    <p id="barcode-info" class="text-xs mt-1 mb-0 text-default"></p>
                </div>
                <div class="grid grid-cols-2 gap-3 mb-3">
                    <div>
                        <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('From') }}</label>
                        <select name="from_location_id" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                            <option value="">{{ __('— None (inbound)') }}</option>
                            @foreach ($locations as $loc)
                                <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('To') }} <span class="text-danger">*</span></label>
                        <select name="to_location_id" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                            @foreach ($locations as $loc)
                                <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Quantity') }} <span class="text-danger">*</span></label>
                    <input type="number" name="qty" step="0.0001" min="0.0001" value="1" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                </div>
                <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer w-full py-3 text-base">
                    <i class="ph ph-check"></i> {{ __('Record Move') }}
                </button>
            </form>
        </div>
    </div>

    <div class="col-span-12 lg:col-span-6">
        <div class="bg-white border border-border-color rounded-md p-4">
            <h2 class="text-base font-bold text-title mb-3">{{ __('How to use') }}</h2>
            <ol class="text-sm text-default space-y-2 list-decimal ps-5">
                <li>{{ __('Focus is on the barcode field by default.') }}</li>
                <li>{{ __('Scan the product barcode with a USB scanner or type it in.') }}</li>
                <li>{{ __('The product name auto-fills below the barcode.') }}</li>
                <li>{{ __('Set From / To locations (leave From empty for pure inbound).') }}</li>
                <li>{{ __('Enter quantity, submit. Move is written to the immutable ledger.') }}</li>
            </ol>
            <div class="mt-4 p-3 bg-light rounded-md">
                <p class="text-xs text-default mb-0">{{ __('Tip: For a full mobile operator PWA, use the Faz 4 sync API from a React Native app.') }}</p>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        var input = document.getElementById('barcode-input');
        var info = document.getElementById('barcode-info');
        if (!input) return;

        var timer = null;
        input.addEventListener('input', function () {
            clearTimeout(timer);
            var value = input.value.trim();
            if (value.length < 4) { info.textContent = ''; info.className = 'text-xs mt-1 mb-0 text-default'; return; }

            timer = setTimeout(function () {
                fetch('{{ route('app.inventory.barcode.lookup') }}?barcode=' + encodeURIComponent(value), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function (r) { return r.status === 200 ? r.json() : null; })
                    .then(function (data) {
                        if (data && data.found) {
                            info.textContent = data.product.name + (data.product.uom ? ' · ' + data.product.uom : '');
                            info.className = 'text-xs mt-1 mb-0 text-success font-semibold';
                        } else {
                            info.textContent = '{{ __('Unknown barcode') }}';
                            info.className = 'text-xs mt-1 mb-0 text-danger';
                        }
                    })
                    .catch(function () { info.textContent = ''; });
            }, 300);
        });
    })();
</script>
@endsection
