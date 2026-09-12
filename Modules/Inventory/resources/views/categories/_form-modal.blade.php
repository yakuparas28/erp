<div id="{{ $id }}" class="hs-overlay hidden size-full fixed top-0 start-0 z-[80] overflow-x-hidden overflow-y-auto pointer-events-none">
    <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-lg sm:w-full m-3 sm:mx-auto pointer-events-auto min-h-screen flex items-center justify-center">
        <form method="POST" action="{{ $action }}" class="flex flex-col bg-white border shadow-sm rounded-md border-border-color w-full">
            @csrf
            @if (($method ?? null) === 'PATCH') @method('PATCH') @endif
            <div class="flex justify-between items-center p-4 border-b border-border-color">
                <h2 class="text-base font-bold text-title">{{ $title }}</h2>
                <button type="button" class="size-7 inline-flex justify-center items-center rounded-md border border-border-color hover:bg-light cursor-pointer" data-hs-overlay="#{{ $id }}" aria-label="{{ __('Cancel') }}"><i class="ph ph-x text-sm"></i></button>
            </div>
            <div class="p-4 grid grid-cols-12 gap-3">
                <div class="col-span-12 sm:col-span-6">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Name') }} <span class="text-danger">*</span></label>
                    <input type="text" name="name" required value="{{ $category->name ?? '' }}" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                </div>
                <div class="col-span-12 sm:col-span-6">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Parent Category') }}</label>
                    <select name="parent_id" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                        <option value="">{{ __('— No parent (root)') }}</option>
                        @foreach ($categories as $parentOption)
                            @if ($category === null || $parentOption->id !== $category->id)
                                <option value="{{ $parentOption->id }}" @selected(($category->parent_id ?? null) === $parentOption->id)>{{ $parentOption->name }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>
                @if ($accounts->isNotEmpty())
                    <p class="col-span-12 text-xs font-semibold text-title mt-2 mb-0">{{ __('Accounting Defaults') }}</p>
                    @foreach (['stock_input_account_id' => __('Stock Input Account'), 'stock_output_account_id' => __('Stock Output Account'), 'expense_account_id' => __('Expense Account'), 'income_account_id' => __('Income Account')] as $field => $label)
                        <div class="col-span-12 sm:col-span-6">
                            <label class="text-xs text-default mb-1 block">{{ $label }}</label>
                            <select name="{{ $field }}" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                                <option value="">—</option>
                                @foreach ($accounts as $account)
                                    <option value="{{ $account->id }}" @selected(($category->{$field} ?? null) === $account->id)>{{ $account->code }} — {{ $account->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach
                @endif
            </div>
            <div class="flex justify-end gap-2 p-4 border-t border-border-color">
                <button type="button" class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light cursor-pointer" data-hs-overlay="#{{ $id }}">{{ __('Cancel') }}</button>
                <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Save') }}</button>
            </div>
        </form>
    </div>
</div>
