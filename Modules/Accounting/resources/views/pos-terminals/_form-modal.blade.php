<div id="pos-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4" role="dialog">
    <div class="bg-white rounded-md shadow-xl w-full max-h-[92vh] overflow-hidden" style="max-width: min(560px, calc(100vw - 32px));">
        <form id="pos-form" method="POST" action="{{ route('app.accounting.pos-terminals.store') }}">
            @csrf
            <div class="flex items-center justify-between border-b border-border-color px-4 py-3">
                <h2 id="pos-title" class="text-base font-bold text-title inline-flex items-center gap-2">
                    <i class="ph ph-credit-card"></i> {{ __('New POS Terminal') }}
                </h2>
                <button type="button" data-pos-close class="text-default hover:text-gray-900 cursor-pointer">
                    <i class="ph ph-x text-lg"></i>
                </button>
            </div>

            <div class="p-4 overflow-y-auto space-y-3">
                <div>
                    <label class="text-[12px] text-default block mb-1">{{ __('Name') }} *</label>
                    <input type="text" name="name" id="pos-name" required maxlength="128" placeholder="Vakıfbank POS #1"
                           class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                </div>

                <div>
                    <label class="text-[12px] text-default block mb-1">{{ __('Bank Account') }} *</label>
                    <select name="bank_journal_id" id="pos-bank" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                        @foreach ($bankJournals as $bj)
                            <option value="{{ $bj->id }}">{{ $bj->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="text-[12px] text-default block mb-1">{{ __('Commission Rates (installment:rate, comma-separated)') }} *</label>
                    <input type="text" name="commission_rates_raw" id="pos-rates" required
                           placeholder="1:1.5, 3:2.5, 6:3.5, 9:4.0, 12:4.75"
                           class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0 font-mono">
                    <p class="text-[11px] text-default mt-1">
                        {{ __('Example: "1:1.5, 3:2.5" → 1 taksitte %1.5, 3 taksitte %2.5.') }}
                    </p>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-[12px] text-default block mb-1">{{ __('Settlement Days (Valör)') }} *</label>
                        <input type="number" min="0" max="60" name="settlement_days" id="pos-days" value="1" required
                               class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                    </div>
                    <div class="flex items-end">
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input type="checkbox" name="is_active" id="pos-active" value="1" checked class="rounded border-border-color">
                            {{ __('Active') }}
                        </label>
                    </div>
                </div>
            </div>

            <div class="border-t border-border-color px-4 py-3 flex items-center justify-end gap-2">
                <button type="button" data-pos-close class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light cursor-pointer">
                    {{ __('Cancel') }}
                </button>
                <button type="submit" class="btn-sm bg-primary text-white border border-primary hover:bg-primary/90 cursor-pointer inline-flex items-center gap-2">
                    <i class="ph ph-floppy-disk"></i> {{ __('Save') }}
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const modal = document.getElementById('pos-modal');
    const form = document.getElementById('pos-form');
    const storeUrl = @json(route('app.accounting.pos-terminals.store'));

    const open = () => { modal.classList.remove('hidden'); modal.classList.add('flex'); };
    const close = () => { modal.classList.remove('flex'); modal.classList.add('hidden'); };
    const reset = () => {
        form.reset();
        form.action = storeUrl;
        form.querySelector('input[name="_method"]')?.remove();
        document.getElementById('pos-title').textContent = @json(__('New POS Terminal'));
    };

    document.querySelectorAll('[data-pos-open-add]').forEach((b) => b.addEventListener('click', () => { reset(); open(); }));
    document.querySelectorAll('[data-pos-close]').forEach((b) => b.addEventListener('click', close));
    modal.addEventListener('click', (e) => { if (e.target === modal) close(); });

    document.querySelectorAll('[data-pos-edit]').forEach((b) => b.addEventListener('click', () => {
        const t = JSON.parse(b.dataset.terminal);
        reset();
        form.action = @json(url('/app/accounting/pos-terminals')) + '/' + t.id;
        const method = document.createElement('input');
        method.type = 'hidden'; method.name = '_method'; method.value = 'PATCH';
        form.appendChild(method);
        document.getElementById('pos-title').textContent = @json(__('Edit')) + ' — ' + t.name;
        form.querySelector('[name="name"]').value = t.name;
        form.querySelector('[name="bank_journal_id"]').value = t.bank_journal_id;
        const ratesArr = Object.entries(t.commission_rates || {}).map(([k, v]) => `${k}:${v}`);
        form.querySelector('[name="commission_rates_raw"]').value = ratesArr.join(', ');
        form.querySelector('[name="settlement_days"]').value = t.settlement_days ?? 1;
        form.querySelector('[name="is_active"]').checked = !!t.is_active;
        open();
    }));
})();
</script>
