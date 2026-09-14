<div id="{{ $id }}" class="hs-overlay hidden size-full fixed top-0 start-0 z-[80] overflow-x-hidden overflow-y-auto pointer-events-none">
    <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-2xl sm:w-full m-3 sm:mx-auto pointer-events-auto min-h-screen flex items-center justify-center" style="max-width: min(960px, calc(100vw - 32px));">
        <form method="POST" action="{{ $action }}" class="flex flex-col bg-white border shadow-sm rounded-md border-border-color w-full max-h-[92vh] overflow-hidden">
            @csrf
            @if ($method === 'PUT') @method('PUT') @endif
            <div class="flex justify-between items-center p-4 border-b border-border-color">
                <h2 class="text-base font-bold text-title">{{ $title }}</h2>
                <button type="button" class="size-7 inline-flex justify-center items-center rounded-md border border-border-color hover:bg-light cursor-pointer" data-hs-overlay="#{{ $id }}" aria-label="{{ __('Cancel') }}"><i class="ph ph-x text-sm"></i></button>
            </div>

            <div class="p-4 overflow-y-auto">
                <h3 class="text-sm font-semibold text-title mb-2">{{ __('Basic') }}</h3>
                <div class="grid grid-cols-12 gap-3 mb-4">
                    <div class="col-span-12">
                        <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Name') }} <span class="text-danger">*</span></label>
                        <input type="text" name="name" required value="{{ $product?->name }}" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                    </div>
                    <div class="col-span-12 sm:col-span-4">
                        <label class="text-sm font-semibold text-gray-900 mb-1 block">SKU</label>
                        <input type="text" name="sku" value="{{ $product?->sku }}" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0 font-mono">
                    </div>
                    <div class="col-span-12 sm:col-span-4">
                        <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Barcode') }}</label>
                        <div class="flex">
                            <input type="text" name="barcode" data-barcode-input value="{{ $product?->barcode }}" placeholder="{{ __('EAN-13, UPC vs.') }}" class="w-full px-3 py-2 text-sm border border-border-color rounded-l-md bg-white focus:outline-none focus:ring-0 font-mono">
                            <div class="hs-dropdown [--placement:bottom-right] [--auto-close:inside] relative inline-flex">
                                <button type="button" class="hs-dropdown-toggle btn-sm h-full px-2 border border-l-0 border-border-color rounded-r-md bg-light hover:bg-white cursor-pointer inline-flex items-center gap-1 text-default" title="{{ __('Auto-generate') }}">
                                    <i class="ph ph-magic-wand"></i><i class="ph ph-caret-down text-xs"></i>
                                </button>
                                <div class="hs-dropdown-menu transition-[opacity,margin] duration hs-dropdown-open:opacity-100 opacity-0 hidden min-w-40 bg-white border border-border-color shadow rounded-md mt-2 z-10" role="menu">
                                    <div class="p-2 space-y-1">
                                        <button type="button" data-barcode-gen="ean13" class="w-full text-left flex items-center gap-2 px-2 py-1.5 rounded-md text-sm text-gray-900 hover:bg-light"><i class="ph ph-barcode"></i> EAN-13</button>
                                        <button type="button" data-barcode-gen="upca" class="w-full text-left flex items-center gap-2 px-2 py-1.5 rounded-md text-sm text-gray-900 hover:bg-light"><i class="ph ph-barcode"></i> UPC-A</button>
                                        <button type="button" data-barcode-gen="ean8" class="w-full text-left flex items-center gap-2 px-2 py-1.5 rounded-md text-sm text-gray-900 hover:bg-light"><i class="ph ph-barcode"></i> EAN-8</button>
                                        <button type="button" data-barcode-gen="code128" class="w-full text-left flex items-center gap-2 px-2 py-1.5 rounded-md text-sm text-gray-900 hover:bg-light"><i class="ph ph-barcode"></i> CODE 128</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-span-12 sm:col-span-4">
                        <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('List Price') }}</label>
                        <input type="number" name="list_price" step="0.0001" min="0" value="{{ $product?->list_price ?? '0' }}" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                    </div>
                    <div class="col-span-12 sm:col-span-6">
                        <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Unit') }} <span class="text-danger">*</span></label>
                        <select name="uom_id" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                            @foreach ($uoms as $uom)
                                <option value="{{ $uom->id }}" @selected($product?->uom_id === $uom->id)>{{ $uom->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-span-12 sm:col-span-6">
                        <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Category') }}</label>
                        <select name="product_category_id" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                            <option value="">—</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected($product?->product_category_id === $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-span-12 sm:col-span-6">
                        <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Brand') }}</label>
                        <select name="product_brand_id" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                            <option value="">—</option>
                            @foreach ($brands as $brand)
                                <option value="{{ $brand->id }}" @selected($product?->product_brand_id === $brand->id)>{{ $brand->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <h3 class="text-sm font-semibold text-title mb-2">{{ __('Inventory') }}</h3>
                <div class="grid grid-cols-12 gap-3 mb-4">
                    <div class="col-span-12 sm:col-span-4">
                        <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Type') }}</label>
                        <select name="product_type" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                            @foreach (['stockable', 'consumable', 'service'] as $type)
                                <option value="{{ $type }}" @selected(($product?->product_type ?? 'stockable') === $type)>{{ __('product-type.'.$type) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-span-12 sm:col-span-4">
                        <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Tracking') }}</label>
                        <select name="track_by" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                            <option value="none" @selected(($product?->track_by ?? 'none') === 'none')>{{ __('None') }}</option>
                            <option value="lot" @selected($product?->track_by === 'lot')>{{ __('Lot') }}</option>
                            <option value="serial" @selected($product?->track_by === 'serial')>{{ __('Serial') }}</option>
                        </select>
                    </div>
                    <div class="col-span-12 sm:col-span-4">
                        <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Reservation Method') }}</label>
                        <select name="reservation_method" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                            <option value="at_confirmation" @selected(($product?->reservation_method ?? 'at_confirmation') === 'at_confirmation')>{{ __('reservation.at_confirmation') }}</option>
                            <option value="manual" @selected($product?->reservation_method === 'manual')>{{ __('reservation.manual') }}</option>
                        </select>
                    </div>
                </div>

                <h3 class="text-sm font-semibold text-title mb-2">{{ __('Flags') }}</h3>
                <div class="grid grid-cols-12 gap-3 mb-4">
                    <div class="col-span-12 sm:col-span-4 flex items-center gap-2">
                        <input type="hidden" name="sale_ok" value="0">
                        <input type="checkbox" id="sale_ok_{{ $id }}" name="sale_ok" value="1" class="size-4 rounded border-border-color" @checked($product?->sale_ok ?? true)>
                        <label for="sale_ok_{{ $id }}" class="text-sm text-default">{{ __('Can be sold') }}</label>
                    </div>
                    <div class="col-span-12 sm:col-span-4 flex items-center gap-2">
                        <input type="hidden" name="purchase_ok" value="0">
                        <input type="checkbox" id="purchase_ok_{{ $id }}" name="purchase_ok" value="1" class="size-4 rounded border-border-color" @checked($product?->purchase_ok ?? true)>
                        <label for="purchase_ok_{{ $id }}" class="text-sm text-default">{{ __('Can be purchased') }}</label>
                    </div>
                    <div class="col-span-12 sm:col-span-4 flex items-center gap-2">
                        <input type="hidden" name="is_kit" value="0">
                        <input type="checkbox" id="is_kit_{{ $id }}" name="is_kit" value="1" class="size-4 rounded border-border-color" @checked($product?->is_kit)>
                        <label for="is_kit_{{ $id }}" class="text-sm text-default">{{ __('Kit / bundle') }}</label>
                    </div>
                </div>

                <h3 class="text-sm font-semibold text-title mb-2">{{ __('Descriptions & Customs') }}</h3>
                <div class="grid grid-cols-12 gap-3">
                    <div class="col-span-12">
                        <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Internal Note') }}</label>
                        <textarea name="description" rows="2" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">{{ $product?->description }}</textarea>
                    </div>
                    <div class="col-span-12">
                        <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Customer Description') }}</label>
                        <textarea name="description_sale" rows="2" placeholder="{{ __('Shown on quotations and invoices') }}" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">{{ $product?->description_sale }}</textarea>
                    </div>
                    <div class="col-span-12 sm:col-span-6">
                        <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('HS Code') }}</label>
                        <input type="text" name="hs_code" value="{{ $product?->hs_code }}" placeholder="8471.30.00" maxlength="20" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0 font-mono">
                    </div>
                    <div class="col-span-12 sm:col-span-6">
                        <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Country of Origin') }}</label>
                        <input type="text" name="country_of_origin" value="{{ $product?->country_of_origin }}" placeholder="TR" maxlength="2" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0 uppercase font-mono">
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2 p-4 border-t border-border-color">
                <button type="button" class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light cursor-pointer" data-hs-overlay="#{{ $id }}">{{ __('Cancel') }}</button>
                <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Save') }}</button>
            </div>
        </form>

        @if ($product !== null)
            <div class="border-t border-border-color p-4 bg-light/40 rounded-b-md">
                <div class="flex items-center gap-3">
                    @if ($product->image_path)
                        <img src="{{ asset('storage/'.$product->image_path) }}" alt="" class="size-14 rounded border border-border-color object-cover shrink-0">
                    @else
                        <div class="size-14 rounded border border-dashed border-border-color flex items-center justify-center text-default shrink-0">
                            <i class="ph ph-image text-lg"></i>
                        </div>
                    @endif
                    <form method="POST" action="{{ route('app.inventory.products.image.upload', $product) }}" enctype="multipart/form-data" class="flex-1 flex items-end gap-2">
                        @csrf
                        <div class="flex-1">
                            <label class="text-xs text-default mb-1 block">{{ __('Image') }} <span class="text-default/70">({{ __('JPG, PNG, WebP · max 2 MB') }})</span></label>
                            <input type="file" name="image" accept="image/jpeg,image/png,image/webp" required class="w-full text-xs file:mr-2 file:px-2 file:py-1 file:border file:border-border-color file:bg-white file:text-xs file:rounded-md file:cursor-pointer">
                        </div>
                        <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Upload') }}</button>
                    </form>
                    @if ($product->image_path)
                        <form method="POST" action="{{ route('app.inventory.products.image.destroy', $product) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="size-8 rounded-md border border-danger text-danger hover:bg-danger hover:text-white cursor-pointer" title="{{ __('Remove image') }}">
                                <i class="ph ph-trash text-sm"></i>
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
<script>
(function () {
    const modal = document.getElementById('{{ $id }}');
    if (!modal || modal.dataset.barcodeWired === '1') return;
    modal.dataset.barcodeWired = '1';
    const input = modal.querySelector('[data-barcode-input]');
    if (!input) return;

    const digits = (n) => Array.from({length: n}, () => Math.floor(Math.random() * 10)).join('');

    // GTIN checksum: from right, positions odd=3x even=1x (EAN-13/UPC-A/EAN-8 all use this rule with pad).
    const gtinCheckDigit = (body) => {
        const arr = body.split('').map(Number);
        let sum = 0;
        for (let i = arr.length - 1, k = 0; i >= 0; i--, k++) {
            sum += arr[i] * (k % 2 === 0 ? 3 : 1);
        }
        return (10 - (sum % 10)) % 10;
    };

    const generators = {
        ean13: () => { const body = digits(12); return body + gtinCheckDigit(body); },
        upca:  () => { const body = digits(11); return body + gtinCheckDigit(body); },
        ean8:  () => { const body = digits(7);  return body + gtinCheckDigit(body); },
        code128: () => {
            const alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
            let out = '';
            for (let i = 0; i < 12; i++) out += alphabet[Math.floor(Math.random() * alphabet.length)];
            return out;
        },
    };

    modal.querySelectorAll('[data-barcode-gen]').forEach(btn => {
        btn.addEventListener('click', () => {
            const type = btn.dataset.barcodeGen;
            const gen = generators[type];
            if (gen) {
                input.value = gen();
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.focus();
            }
        });
    });
})();
</script>

