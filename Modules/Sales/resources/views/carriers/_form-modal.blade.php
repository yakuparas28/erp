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
                    <input type="text" name="name" required value="{{ $carrier->name ?? '' }}" placeholder="{{ __('e.g. Aras Kargo') }}" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                </div>
                <div class="col-span-12 sm:col-span-4">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Code') }}</label>
                    <input type="text" name="code" maxlength="32" value="{{ $carrier->code ?? '' }}" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0 font-mono">
                </div>
                <div class="col-span-12">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Tracking URL Template') }}</label>
                    <input type="url" name="tracking_url_template" value="{{ $carrier->tracking_url_template ?? '' }}" placeholder="https://kargo.example.com/takip?no={tracking_number}" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0 font-mono">
                    <p class="text-xs text-default mt-1 mb-0">{{ __('Use {tracking_number} as placeholder for the tracking code.') }}</p>
                </div>
                @if ($carrier !== null)
                    <div class="col-span-12">
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input type="checkbox" name="active" value="1" @checked($carrier->active) class="rounded border-border-color">
                            <span>{{ __('Active') }}</span>
                        </label>
                    </div>
                @endif
            </div>
            <div class="flex justify-end gap-2 p-4 border-t border-border-color">
                <button type="button" class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light cursor-pointer" data-hs-overlay="#{{ $id }}">{{ __('Cancel') }}</button>
                <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Save') }}</button>
            </div>
        </form>
    </div>
</div>
