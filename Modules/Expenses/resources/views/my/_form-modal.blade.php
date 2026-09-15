<div id="{{ $id }}" class="hs-overlay hidden size-full fixed top-0 start-0 z-[80] overflow-x-hidden overflow-y-auto pointer-events-none">
    <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-2xl sm:w-full m-3 sm:mx-auto pointer-events-auto min-h-screen flex items-center justify-center" style="max-width: min(720px, calc(100vw - 32px));">
        <form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="flex flex-col bg-white border shadow-sm rounded-md border-border-color w-full">
            @csrf
            @if ($method === 'PUT') @method('PUT') @endif
            <div class="flex justify-between items-center p-4 border-b border-border-color">
                <h2 class="text-base font-bold text-title">{{ $title }}</h2>
                <button type="button" class="size-7 inline-flex justify-center items-center rounded-md border border-border-color hover:bg-light cursor-pointer" data-hs-overlay="#{{ $id }}"><i class="ph ph-x text-sm"></i></button>
            </div>
            <div class="p-4 grid grid-cols-12 gap-3">
                <div class="col-span-12">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Description') }} <span class="text-danger">*</span></label>
                    <input type="text" name="description" required maxlength="255" value="{{ $expense?->description }}" placeholder="{{ __('e.g. Lunch with client') }}" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                </div>
                <div class="col-span-12 sm:col-span-6">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Category') }} <span class="text-danger">*</span></label>
                    <select name="expense_category_id" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                        <option value="">—</option>
                        @foreach ($categories as $c)
                            <option value="{{ $c->id }}" data-flat="{{ $c->isFlatRate() ? 1 : 0 }}" data-unit-price="{{ $c->unit_price }}" data-unit-label="{{ $c->unit_label }}" @selected($expense?->expense_category_id === $c->id)>{{ $c->name }} @if($c->isFlatRate())({{ number_format((float)$c->unit_price,2) }}/{{ $c->unit_label }})@endif</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-span-12 sm:col-span-6">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Expense Date') }} <span class="text-danger">*</span></label>
                    <input type="date" name="expense_date" required max="{{ today()->toDateString() }}" value="{{ $expense?->expense_date?->toDateString() ?? today()->toDateString() }}" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                </div>
                <div class="col-span-6 sm:col-span-3">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Quantity') }}</label>
                    <input type="number" name="qty" step="0.01" min="0.01" required value="{{ $expense?->qty ?? '1' }}" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                </div>
                <div class="col-span-6 sm:col-span-3">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Unit Price') }}</label>
                    <input type="number" name="unit_price" step="0.0001" min="0" value="{{ $expense?->unit_price }}" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                </div>
                <div class="col-span-6 sm:col-span-3">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Currency') }}</label>
                    <select name="currency_code" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                        @foreach (['TRY', 'USD', 'EUR', 'GBP'] as $c)
                            <option value="{{ $c }}" @selected(($expense?->currency_code ?? 'TRY') === $c)>{{ $c }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-span-6 sm:col-span-3">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Paid By') }} <span class="text-danger">*</span></label>
                    <select name="paid_by" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                        <option value="employee" @selected(($expense?->paid_by ?? 'employee') === 'employee')>{{ __('paid-by.employee') }}</option>
                        <option value="company" @selected($expense?->paid_by === 'company')>{{ __('paid-by.company') }}</option>
                    </select>
                </div>
                <div class="col-span-12">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Reference') }}</label>
                    <input type="text" name="reference" maxlength="64" value="{{ $expense?->reference }}" placeholder="{{ __('e.g. Invoice #12345') }}" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                </div>
                <div class="col-span-12">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Notes') }}</label>
                    <textarea name="notes" rows="2" maxlength="2000" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">{{ $expense?->notes }}</textarea>
                </div>
                <div class="col-span-12">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Receipt') }} <span class="text-[10px] text-default">({{ __('JPG, PNG, WebP · max 8 MB') }})</span></label>
                    <input type="file" name="receipt" accept="image/*" class="w-full text-xs file:mr-2 file:px-2 file:py-1 file:border file:border-border-color file:bg-white file:text-xs file:rounded-md file:cursor-pointer">
                    @if ($expense?->receipt_path)<div class="text-[10px] text-default mt-1"><i class="ph ph-paperclip"></i> {{ __('Current receipt attached') }} — <a href="{{ \Storage::url($expense->receipt_path) }}" target="_blank" class="text-primary hover:underline">{{ __('view') }}</a></div>@endif
                </div>
            </div>
            <div class="flex justify-end gap-2 p-4 border-t border-border-color">
                <button type="button" class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light cursor-pointer" data-hs-overlay="#{{ $id }}">{{ __('Cancel') }}</button>
                <button class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Save') }}</button>
            </div>
        </form>
    </div>
</div>
<script>
(function () {
    const modal = document.getElementById('{{ $id }}');
    if (!modal || modal.dataset.expenseWired === '1') return;
    modal.dataset.expenseWired = '1';
    const catSelect = modal.querySelector('select[name="expense_category_id"]');
    const unitPriceInput = modal.querySelector('input[name="unit_price"]');
    catSelect?.addEventListener('change', () => {
        const opt = catSelect.options[catSelect.selectedIndex];
        if (opt?.dataset.flat === '1') {
            unitPriceInput.value = opt.dataset.unitPrice;
            unitPriceInput.readOnly = true;
            unitPriceInput.classList.add('bg-light', 'text-default');
        } else {
            unitPriceInput.readOnly = false;
            unitPriceInput.classList.remove('bg-light', 'text-default');
        }
    });
    // Initial sync
    if (catSelect?.value) catSelect.dispatchEvent(new Event('change'));
})();
</script>
