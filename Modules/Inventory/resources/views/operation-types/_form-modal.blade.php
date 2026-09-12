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
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Warehouse') }} <span class="text-danger">*</span></label>
                    <select name="warehouse_id" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                        @foreach ($warehouses as $w)
                            <option value="{{ $w->id }}" @selected(($type->warehouse_id ?? null) === $w->id)>{{ $w->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-span-6 sm:col-span-3">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Code') }} <span class="text-danger">*</span></label>
                    <input type="text" name="code" required maxlength="32" value="{{ $type->code ?? '' }}" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0 font-mono">
                </div>
                <div class="col-span-6 sm:col-span-3">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Prefix') }}</label>
                    <input type="text" name="sequence_prefix" maxlength="16" value="{{ $type->sequence_prefix ?? '' }}" placeholder="WH/OUT" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0 font-mono">
                </div>
                <div class="col-span-12 sm:col-span-6">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Name') }} <span class="text-danger">*</span></label>
                    <input type="text" name="name" required value="{{ $type->name ?? '' }}" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                </div>
                <div class="col-span-12 sm:col-span-6">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Type') }} <span class="text-danger">*</span></label>
                    <select name="type" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                        @foreach (['incoming', 'outgoing', 'internal', 'scrap'] as $t)
                            <option value="{{ $t }}" @selected(($type->type ?? 'internal') === $t)>{{ __('operation-type.'.$t) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-span-12 sm:col-span-6">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Default Source') }}</label>
                    <select name="default_source_location_id" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                        <option value="">—</option>
                        @foreach ($locations as $loc)
                            <option value="{{ $loc->id }}" @selected(($type->default_source_location_id ?? null) === $loc->id)>{{ $loc->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-span-12 sm:col-span-6">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Default Destination') }}</label>
                    <select name="default_destination_location_id" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                        <option value="">—</option>
                        @foreach ($locations as $loc)
                            <option value="{{ $loc->id }}" @selected(($type->default_destination_location_id ?? null) === $loc->id)>{{ $loc->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="flex justify-end gap-2 p-4 border-t border-border-color">
                <button type="button" class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light cursor-pointer" data-hs-overlay="#{{ $id }}">{{ __('Cancel') }}</button>
                <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Save') }}</button>
            </div>
        </form>
    </div>
</div>
