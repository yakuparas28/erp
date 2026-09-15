{{-- Kasa / Banka hesabı ekle-düzenle modalı. --}}
<div id="cb-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4" role="dialog">
    <div class="bg-white rounded-md shadow-xl w-full max-h-[92vh] overflow-hidden" style="max-width: min(560px, calc(100vw - 32px));">
        <form id="cb-form" method="POST" action="{{ route('app.accounting.cash-bank-accounts.store') }}">
            @csrf
            <div class="flex items-center justify-between border-b border-border-color px-4 py-3">
                <h2 id="cb-title" class="text-base font-bold text-title inline-flex items-center gap-2">
                    <i class="ph ph-vault"></i> {{ __('New Cash / Bank Account') }}
                </h2>
                <button type="button" data-cb-close class="text-default hover:text-gray-900 cursor-pointer">
                    <i class="ph ph-x text-lg"></i>
                </button>
            </div>

            <div class="p-4 overflow-y-auto space-y-3">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-[12px] text-default block mb-1">{{ __('Type') }} *</label>
                        <select name="type" id="cb-type" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                            <option value="cash">{{ __('Cash') }}</option>
                            <option value="bank">{{ __('Bank') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-[12px] text-default block mb-1">{{ __('Code') }}</label>
                        <input type="text" name="code" id="cb-code" maxlength="32" placeholder="KASA-01"
                               class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0 font-mono">
                    </div>
                </div>

                <div>
                    <label class="text-[12px] text-default block mb-1">{{ __('Name') }} *</label>
                    <input type="text" name="name" id="cb-name" required maxlength="128" placeholder="Merkez Kasa / Vakıfbank TL"
                           class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                </div>

                <div id="cb-bank-fields" class="grid grid-cols-2 gap-3 hidden">
                    <div class="col-span-2">
                        <label class="text-[12px] text-default block mb-1">{{ __('Bank Name') }}</label>
                        <input type="text" name="bank_name" id="cb-bank-name" maxlength="128" placeholder="Vakıfbank"
                               class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                    </div>
                    <div>
                        <label class="text-[12px] text-default block mb-1">{{ __('IBAN') }}</label>
                        <input type="text" name="iban" id="cb-iban" maxlength="34" placeholder="TR00 0000 0000 0000 0000 0000 00"
                               class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0 font-mono">
                    </div>
                    <div>
                        <label class="text-[12px] text-default block mb-1">{{ __('Account No') }}</label>
                        <input type="text" name="account_no" id="cb-account-no" maxlength="64"
                               class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0 font-mono">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-[12px] text-default block mb-1">{{ __('Currency') }}</label>
                        <select name="currency_id" id="cb-currency" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                            <option value="">{{ __('TRY (default)') }}</option>
                            @foreach ($currencies as $currency)
                                <option value="{{ $currency->id }}">{{ $currency->code }} — {{ $currency->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-[12px] text-default block mb-1">{{ __('Chart of Account') }}</label>
                        <select name="chart_of_account_id" id="cb-coa" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                            <option value="">{{ __('— (use default)') }}</option>
                            <optgroup label="{{ __('Cash (100.x)') }}">
                                @foreach ($cashAccounts as $account)
                                    <option value="{{ $account->id }}" data-type="cash">{{ $account->code }} — {{ $account->name }}</option>
                                @endforeach
                            </optgroup>
                            <optgroup label="{{ __('Bank (102.x)') }}">
                                @foreach ($bankAccounts as $account)
                                    <option value="{{ $account->id }}" data-type="bank">{{ $account->code }} — {{ $account->name }}</option>
                                @endforeach
                            </optgroup>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-[12px] text-default block mb-1">{{ __('Opening Balance') }}</label>
                        <input type="number" step="0.01" name="opening_balance" id="cb-ob" value="0"
                               class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0 text-right">
                    </div>
                    <div class="flex items-end">
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input type="checkbox" name="is_active" id="cb-active" value="1" checked class="rounded border-border-color">
                            {{ __('Active') }}
                        </label>
                    </div>
                </div>
            </div>

            <div class="border-t border-border-color px-4 py-3 flex items-center justify-end gap-2">
                <button type="button" data-cb-close class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light cursor-pointer">
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
    const modal = document.getElementById('cb-modal');
    const form = document.getElementById('cb-form');
    const bankFields = document.getElementById('cb-bank-fields');
    const typeSelect = document.getElementById('cb-type');
    const storeUrl = @json(route('app.accounting.cash-bank-accounts.store'));

    const open = () => { modal.classList.remove('hidden'); modal.classList.add('flex'); };
    const close = () => { modal.classList.remove('flex'); modal.classList.add('hidden'); };

    const toggleBankFields = () => {
        bankFields.classList.toggle('hidden', typeSelect.value !== 'bank');
    };
    typeSelect.addEventListener('change', toggleBankFields);

    const reset = () => {
        form.reset();
        form.action = storeUrl;
        form.querySelector('input[name="_method"]')?.remove();
        document.getElementById('cb-title').textContent = @json(__('New Cash / Bank Account'));
        toggleBankFields();
    };

    document.querySelectorAll('[data-cb-open-add]').forEach((btn) => btn.addEventListener('click', () => { reset(); open(); }));
    document.querySelectorAll('[data-cb-close]').forEach((btn) => btn.addEventListener('click', close));
    modal.addEventListener('click', (e) => { if (e.target === modal) close(); });

    document.querySelectorAll('[data-cb-edit]').forEach((btn) => btn.addEventListener('click', () => {
        const a = JSON.parse(btn.dataset.account);
        reset();
        form.action = @json(url('/app/accounting/cash-bank-accounts')) + '/' + a.id;
        const method = document.createElement('input');
        method.type = 'hidden'; method.name = '_method'; method.value = 'PATCH';
        form.appendChild(method);
        document.getElementById('cb-title').textContent = @json(__('Edit')) + ' — ' + a.name;
        form.querySelector('[name="type"]').value = a.type;
        form.querySelector('[name="code"]').value = a.code || '';
        form.querySelector('[name="name"]').value = a.name;
        form.querySelector('[name="bank_name"]').value = a.bank_name || '';
        form.querySelector('[name="iban"]').value = a.iban || '';
        form.querySelector('[name="account_no"]').value = a.account_no || '';
        form.querySelector('[name="currency_id"]').value = a.currency_id || '';
        form.querySelector('[name="chart_of_account_id"]').value = a.chart_of_account_id || '';
        form.querySelector('[name="opening_balance"]').value = a.opening_balance || 0;
        form.querySelector('[name="is_active"]').checked = !!a.is_active;
        toggleBankFields();
        open();
    }));
})();
</script>
