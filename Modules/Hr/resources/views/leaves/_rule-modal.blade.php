<div id="{{ $id }}" class="hs-overlay hidden size-full fixed top-0 start-0 z-[80] overflow-x-hidden overflow-y-auto pointer-events-none">
    <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-lg sm:w-full m-3 sm:mx-auto pointer-events-auto min-h-screen flex items-center justify-center">
        <form method="POST" action="{{ $action }}" class="flex flex-col bg-white border shadow-sm rounded-md border-border-color w-full">
            @csrf
            @if (($method ?? null) === 'PATCH') @method('PATCH') @endif
            <div class="flex justify-between items-center p-4 border-b border-border-color">
                <h2 class="text-base font-bold text-title">{{ $title }}</h2>
                <button type="button" class="size-7 inline-flex justify-center items-center rounded-md border border-border-color hover:bg-light cursor-pointer" data-hs-overlay="#{{ $id }}"><i class="ph ph-x text-sm"></i></button>
            </div>
            <div class="p-4 grid grid-cols-12 gap-3">
                <div class="col-span-12 sm:col-span-5">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Code') }} <span class="text-danger">*</span></label>
                    <input type="text" name="code" required maxlength="40" value="{{ $rule?->code }}" placeholder="BR-IT-01" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0 font-mono">
                </div>
                <div class="col-span-12 sm:col-span-7">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Category') }} <span class="text-danger">*</span></label>
                    <select name="category" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                        <option value="consumption" @selected($rule?->category === 'consumption')>{{ __('Consumption') }}</option>
                        <option value="accrual" @selected($rule?->category === 'accrual')>{{ __('Accrual') }}</option>
                    </select>
                </div>
                <div class="col-span-12">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Name') }} <span class="text-danger">*</span></label>
                    <input type="text" name="name" required maxlength="200" value="{{ $rule?->name }}" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                </div>
                <div class="col-span-12">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Legal Basis') }}</label>
                    <input type="text" name="legal_basis" maxlength="500" value="{{ $rule?->legal_basis }}" placeholder="4857 Sayılı İş Kanunu Md. 55" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                </div>
                <div class="col-span-12">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Description') }}</label>
                    <textarea name="description" rows="3" maxlength="2000" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">{{ $rule?->description }}</textarea>
                </div>
                <div class="col-span-12">
                    <label class="flex items-center gap-2 text-sm text-gray-900">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" @checked($rule === null || $rule->is_active)>
                        {{ __('Active') }}
                    </label>
                </div>
            </div>
            <div class="flex justify-end gap-2 p-4 border-t border-border-color">
                <button type="button" class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light cursor-pointer" data-hs-overlay="#{{ $id }}">{{ __('Cancel') }}</button>
                <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Save') }}</button>
            </div>
        </form>
    </div>
</div>
