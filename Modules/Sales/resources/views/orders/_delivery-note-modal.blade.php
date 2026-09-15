{{-- Çoklu satır → tek irsaliye modalı. Vanilla JS toggle (Preline yok). --}}
<div id="delivery-note-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4" role="dialog">
    <div class="bg-white rounded-md shadow-xl w-full max-h-[92vh] overflow-hidden" style="max-width: min(720px, calc(100vw - 32px));">
        <form method="POST" action="{{ route('app.sales.orders.delivery-notes.create', $so) }}" class="flex flex-col h-full">
            @csrf
            <div class="flex items-center justify-between border-b border-border-color px-4 py-3">
                <h2 class="text-base font-bold text-title inline-flex items-center gap-2">
                    <i class="ph ph-truck"></i> {{ __('Create Delivery Note') }}
                </h2>
                <button type="button" data-so-close-delivery class="text-default hover:text-gray-900 cursor-pointer">
                    <i class="ph ph-x text-lg"></i>
                </button>
            </div>

            <div class="p-4 overflow-y-auto space-y-4">
                @error('delivery')
                    <div class="bg-danger-transparent text-danger border border-danger rounded-md px-3 py-2 text-sm">{{ $message }}</div>
                @enderror

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="text-[12px] text-default block mb-1">{{ __('Delivery Date') }}</label>
                        <input type="date" name="delivery_date" value="{{ old('delivery_date', now()->toDateString()) }}"
                               class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                    </div>
                    <div>
                        <label class="text-[12px] text-default block mb-1">{{ __('Driver') }}</label>
                        <input type="text" name="driver_name" value="{{ old('driver_name') }}" placeholder="{{ __('e.g. Ahmet Yılmaz') }}"
                               class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                    </div>
                    <div>
                        <label class="text-[12px] text-default block mb-1">{{ __('Vehicle Plate') }}</label>
                        <input type="text" name="vehicle_plate" value="{{ old('vehicle_plate') }}" placeholder="34 ABC 1234"
                               class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                    </div>
                </div>

                <div>
                    <label class="text-[12px] text-default block mb-2 font-semibold">{{ __('Lines to Ship') }}</label>
                    <table class="w-full text-sm border border-border-color rounded-md">
                        <thead class="bg-light">
                            <tr class="text-[11px] uppercase text-default">
                                <th class="text-left py-2 px-2 w-8"></th>
                                <th class="text-left py-2 px-2">{{ __('Product') }}</th>
                                <th class="text-right py-2 px-2">{{ __('Remaining') }}</th>
                                <th class="text-right py-2 px-2 w-32">{{ __('Ship Qty') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $idx = 0; @endphp
                            @foreach ($so->lines as $line)
                                @php $remaining = bcsub((string) $line->qty, (string) $line->delivered_qty, 4); @endphp
                                @if (bccomp($remaining, '0', 4) > 0)
                                    <tr class="border-t border-border-color">
                                        <td class="py-2 px-2">
                                            <input type="checkbox" data-line-check class="rounded border-border-color" data-idx="{{ $idx }}" checked>
                                            <input type="hidden" name="lines[{{ $idx }}][line_id]" value="{{ $line->id }}">
                                        </td>
                                        <td class="py-2 px-2 text-gray-900">{{ $line->product->name }}</td>
                                        <td class="py-2 px-2 text-right text-default">{{ number_format((float) $remaining, 4, ',', '.') }} {{ $line->uom->name }}</td>
                                        <td class="py-2 px-2 text-right">
                                            <input type="number" step="0.0001" min="0.0001" max="{{ $remaining }}"
                                                   name="lines[{{ $idx }}][qty]" value="{{ $remaining }}" required
                                                   class="w-28 px-2 py-1 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0 text-right">
                                        </td>
                                    </tr>
                                    @php $idx++; @endphp
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div>
                    <label class="text-[12px] text-default block mb-1">{{ __('Notes') }}</label>
                    <textarea name="notes" rows="2" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">{{ old('notes') }}</textarea>
                </div>
            </div>

            <div class="border-t border-border-color px-4 py-3 flex items-center justify-end gap-2">
                <button type="button" data-so-close-delivery class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light cursor-pointer">
                    {{ __('Cancel') }}
                </button>
                <button type="submit" class="btn-sm bg-primary text-white border border-primary hover:bg-primary/90 cursor-pointer inline-flex items-center gap-2">
                    <i class="ph ph-check"></i> {{ __('Create Delivery Note') }}
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const modal = document.getElementById('delivery-note-modal');
    if (!modal) return;

    const open = () => {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    };
    const close = () => {
        modal.classList.remove('flex');
        modal.classList.add('hidden');
    };

    document.querySelectorAll('[data-so-open-delivery]').forEach((btn) => btn.addEventListener('click', open));
    document.querySelectorAll('[data-so-close-delivery]').forEach((btn) => btn.addEventListener('click', close));
    modal.addEventListener('click', (e) => { if (e.target === modal) close(); });

    // Checkbox toggle — unchecked satırlar submit'te temizlensin diye disable
    modal.querySelectorAll('[data-line-check]').forEach((cb) => {
        const idx = cb.dataset.idx;
        const row = cb.closest('tr');
        const inputs = row.querySelectorAll('input[name^="lines["]');
        cb.addEventListener('change', () => {
            inputs.forEach((inp) => { inp.disabled = !cb.checked; });
        });
    });
})();
</script>
