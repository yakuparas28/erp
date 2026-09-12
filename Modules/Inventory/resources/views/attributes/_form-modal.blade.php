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
                <div class="col-span-12 sm:col-span-8">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Name') }} <span class="text-danger">*</span></label>
                    <input type="text" name="name" required value="{{ $attribute->name ?? '' }}" placeholder="{{ __('e.g. Renk') }}" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                </div>
                <div class="col-span-12 sm:col-span-4">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Seq.') }}</label>
                    <input type="number" name="sequence" step="1" min="0" value="{{ $attribute->sequence ?? 0 }}" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                </div>
                @if ($attribute === null)
                    <div class="col-span-12 sm:col-span-6">
                        <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Creation Mode') }} <span class="text-danger">*</span></label>
                        <select name="creation_mode" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                            <option value="instant" selected>{{ __('attribute-mode.instant') }}</option>
                            <option value="dynamic">{{ __('attribute-mode.dynamic') }}</option>
                            <option value="never">{{ __('attribute-mode.never') }}</option>
                        </select>
                    </div>
                @endif
                <div class="col-span-12 sm:col-span-6">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Display Type') }} <span class="text-danger">*</span></label>
                    <select name="display_type" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                        @foreach (['select', 'radio', 'pill', 'color'] as $type)
                            <option value="{{ $type }}" @selected(($attribute->display_type ?? 'select') === $type)>{{ __('display-type.'.$type) }}</option>
                        @endforeach
                    </select>
                </div>
                @if ($attribute !== null)
                    <p class="col-span-12 text-xs text-default mb-0">{{ __('Creation mode cannot be changed after values are added.') }}</p>
                @endif
            </div>
            <div class="flex justify-end gap-2 p-4 border-t border-border-color">
                <button type="button" class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light cursor-pointer" data-hs-overlay="#{{ $id }}">{{ __('Cancel') }}</button>
                <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Save') }}</button>
            </div>
        </form>
    </div>
</div>
