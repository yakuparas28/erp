<div id="edit-value-modal-{{ $value->id }}" class="hs-overlay hidden size-full fixed top-0 start-0 z-[80] overflow-x-hidden overflow-y-auto pointer-events-none">
    <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-lg sm:w-full m-3 sm:mx-auto pointer-events-auto min-h-screen flex items-center justify-center">
        <div class="flex flex-col bg-white border shadow-sm rounded-md border-border-color w-full">
            <div class="flex justify-between items-center p-4 border-b border-border-color">
                <div class="flex items-center gap-2">
                    <h2 class="text-base font-bold text-title mb-0">{{ __('Edit Value') }} — {{ $value->value }}</h2>
                    @if ($attribute->display_type === 'color' && $value->html_color)
                        <span class="size-5 rounded-full border border-border-color" style="background: {{ $value->html_color }}"></span>
                    @elseif ($value->image_path)
                        <img src="{{ asset('storage/'.$value->image_path) }}" alt="" class="size-6 rounded object-cover border border-border-color">
                    @endif
                </div>
                <button type="button" class="size-7 inline-flex justify-center items-center rounded-md border border-border-color hover:bg-light cursor-pointer" data-hs-overlay="#edit-value-modal-{{ $value->id }}" aria-label="{{ __('Cancel') }}"><i class="ph ph-x text-sm"></i></button>
            </div>

            <form method="POST" action="{{ route('app.inventory.attribute-values.update', $value) }}">
                @csrf
                @method('PATCH')
                <div class="p-4 grid grid-cols-12 gap-3">
                    <div class="col-span-12">
                        <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Value') }} <span class="text-danger">*</span></label>
                        <input type="text" name="value" required value="{{ $value->value }}" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                    </div>
                    <div class="col-span-6 {{ $attribute->display_type === 'color' ? 'sm:col-span-3' : 'sm:col-span-6' }}">
                        <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Price Extra') }}</label>
                        <input type="number" name="price_extra" step="0.0001" value="{{ $value->price_extra }}" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                    </div>
                    <div class="col-span-6 {{ $attribute->display_type === 'color' ? 'sm:col-span-3' : 'sm:col-span-6' }}">
                        <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Seq.') }}</label>
                        <input type="number" name="sequence" step="1" min="0" value="{{ $value->sequence }}" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                    </div>
                    @if ($attribute->display_type === 'color')
                        <div class="col-span-12 sm:col-span-6">
                            <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Color') }}</label>
                            <input type="color" name="html_color" value="{{ $value->html_color ?? '#000000' }}" class="w-full h-9 border border-border-color rounded-md cursor-pointer">
                        </div>
                    @endif
                    <div class="col-span-12">
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input type="checkbox" name="is_custom" value="1" @checked($value->is_custom) class="rounded border-border-color">
                            <span>{{ __('Allow custom user input for this value') }}</span>
                        </label>
                    </div>
                </div>
                <div class="flex justify-end gap-2 p-4 border-t border-border-color">
                    <button type="button" class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light cursor-pointer" data-hs-overlay="#edit-value-modal-{{ $value->id }}">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Save') }}</button>
                </div>
            </form>

            <div class="border-t border-border-color p-4 rounded-b-md">
                <div class="flex items-center gap-3">
                    @if ($value->image_path)
                        <img src="{{ asset('storage/'.$value->image_path) }}" alt="" class="size-14 rounded border border-border-color object-cover shrink-0">
                    @else
                        <div class="size-14 rounded border border-dashed border-border-color flex items-center justify-center text-default shrink-0">
                            <i class="ph ph-image text-lg"></i>
                        </div>
                    @endif
                    <form method="POST" action="{{ route('app.inventory.attribute-values.image.upload', $value) }}" enctype="multipart/form-data" class="flex-1 flex items-end gap-2">
                        @csrf
                        <div class="flex-1">
                            <label class="text-xs text-default mb-1 block">{{ __('Image') }} <span class="text-default/70">({{ __('JPG, PNG, WebP · max 1 MB') }})</span></label>
                            <input type="file" name="image" accept="image/jpeg,image/png,image/webp" required class="w-full text-xs file:mr-2 file:px-2 file:py-1 file:border file:border-border-color file:bg-white file:text-xs file:rounded-md file:cursor-pointer">
                        </div>
                        <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Upload') }}</button>
                    </form>
                    @if ($value->image_path)
                        <form method="POST" action="{{ route('app.inventory.attribute-values.image.destroy', $value) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="size-8 rounded-md border border-danger text-danger hover:bg-danger hover:text-white cursor-pointer" title="{{ __('Remove image') }}">
                                <i class="ph ph-trash text-sm"></i>
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
