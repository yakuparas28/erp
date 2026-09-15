<div id="{{ $id }}" class="hs-overlay hidden size-full fixed top-0 start-0 z-[80] overflow-x-hidden overflow-y-auto pointer-events-none">
    <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-2xl sm:w-full m-3 sm:mx-auto pointer-events-auto min-h-screen flex items-center justify-center" style="max-width: min(720px, calc(100vw - 32px));">
        <form method="POST" action="{{ $action }}" class="flex flex-col bg-white border shadow-sm rounded-md border-border-color w-full">
            @csrf
            @if ($method === 'PUT') @method('PUT') @endif
            <div class="flex justify-between items-center p-4 border-b border-border-color">
                <h2 class="text-base font-bold text-title">{{ $title }}</h2>
                <button type="button" class="size-7 inline-flex justify-center items-center rounded-md border border-border-color hover:bg-light cursor-pointer" data-hs-overlay="#{{ $id }}"><i class="ph ph-x text-sm"></i></button>
            </div>
            <div class="p-4 grid grid-cols-12 gap-3">
                <div class="col-span-4">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Code') }}</label>
                    <input type="text" name="code" maxlength="32" value="{{ $category?->code }}" placeholder="YEM" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white font-mono focus:outline-none focus:ring-0">
                </div>
                <div class="col-span-8">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Name') }} <span class="text-danger">*</span></label>
                    <input type="text" name="name" required maxlength="128" value="{{ $category?->name }}" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                </div>
                <div class="col-span-6">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Unit Price') }} <span class="text-default text-[10px]">({{ __('0 = actual cost') }})</span></label>
                    <input type="number" name="unit_price" step="0.0001" min="0" value="{{ $category?->unit_price ?? '0' }}" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                </div>
                <div class="col-span-6">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Unit') }}</label>
                    <input type="text" name="unit_label" maxlength="32" value="{{ $category?->unit_label ?? 'Adet' }}" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                </div>
                <div class="col-span-12">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Expense Account Code') }}</label>
                    <input type="text" name="expense_account_code" maxlength="32" value="{{ $category?->expense_account_code }}" placeholder="770.10" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white font-mono focus:outline-none focus:ring-0">
                </div>
                <div class="col-span-12">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Description') }}</label>
                    <textarea name="description" rows="2" maxlength="2000" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">{{ $category?->description }}</textarea>
                </div>
                <div class="col-span-12 flex items-center gap-6">
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="hidden" name="is_reinvoiceable" value="0">
                        <input type="checkbox" name="is_reinvoiceable" value="1" class="size-4 rounded border-border-color" @checked($category?->is_reinvoiceable)>
                        <span>{{ __('Reinvoiceable to customer') }}</span>
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" class="size-4 rounded border-border-color" @checked($category?->is_active ?? true)>
                        <span>{{ __('Active') }}</span>
                    </label>
                </div>
            </div>
            <div class="flex justify-end gap-2 p-4 border-t border-border-color">
                <button type="button" class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light cursor-pointer" data-hs-overlay="#{{ $id }}">{{ __('Cancel') }}</button>
                <button class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Save') }}</button>
            </div>
        </form>
    </div>
</div>
