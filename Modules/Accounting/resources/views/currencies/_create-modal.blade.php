<div id="add-currency-modal" class="hs-overlay hidden size-full fixed top-0 start-0 z-[80] overflow-x-hidden overflow-y-auto pointer-events-none">
    <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-lg sm:w-full m-3 sm:mx-auto pointer-events-auto min-h-screen flex items-center justify-center">
        <form method="POST" action="{{ route('app.accounting.currencies.store') }}" class="flex flex-col bg-white border shadow-sm rounded-md border-border-color w-full">
            @csrf
            <div class="flex justify-between items-center p-4 border-b border-border-color">
                <h2 class="text-base font-bold text-title">{{ __('New Currency') }}</h2>
                <button type="button" class="size-7 inline-flex justify-center items-center rounded-md border border-border-color hover:bg-light cursor-pointer" data-hs-overlay="#add-currency-modal" aria-label="{{ __('Cancel') }}"><i class="ph ph-x text-sm"></i></button>
            </div>
            <div class="p-4 grid grid-cols-12 gap-3">
                <div class="col-span-12 sm:col-span-4">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Code') }} <span class="text-danger">*</span></label>
                    <input type="text" name="code" required maxlength="3" pattern="[A-Za-z]{3}" placeholder="GBP" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0 uppercase">
                    @error('code')
                        <p class="text-[11px] text-danger mt-1 mb-0">{{ $message }}</p>
                    @enderror
                </div>
                <div class="col-span-12 sm:col-span-8">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Name') }} <span class="text-danger">*</span></label>
                    <input type="text" name="name" required placeholder="{{ __('e.g. Pound Sterling') }}" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                    @error('name')
                        <p class="text-[11px] text-danger mt-1 mb-0">{{ $message }}</p>
                    @enderror
                </div>
                <div class="col-span-12 sm:col-span-4">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Symbol') }}</label>
                    <input type="text" name="symbol" maxlength="8" placeholder="£" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                </div>
                <div class="col-span-12 sm:col-span-4">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Symbol Position') }} <span class="text-danger">*</span></label>
                    <select name="position" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                        <option value="before">{{ __('Before amount') }}</option>
                        <option value="after" selected>{{ __('After amount') }}</option>
                    </select>
                </div>
                <div class="col-span-12 sm:col-span-4">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Rounding') }} <span class="text-danger">*</span></label>
                    <input type="number" name="rounding" required step="0.000001" min="0.000001" value="0.01" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                </div>
                <p class="col-span-12 text-xs text-default mb-0">{{ __('The functional currency (TRY) is provisioned automatically and cannot be added or removed here.') }}</p>
            </div>
            <div class="flex justify-end gap-2 p-4 border-t border-border-color">
                <button type="button" class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light cursor-pointer" data-hs-overlay="#add-currency-modal">{{ __('Cancel') }}</button>
                <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Save') }}</button>
            </div>
        </form>
    </div>
</div>
