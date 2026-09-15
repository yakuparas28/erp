<div id="{{ $id }}" class="hs-overlay hidden size-full fixed top-0 start-0 z-[80] overflow-x-hidden overflow-y-auto pointer-events-none">
    <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-2xl sm:w-full m-3 sm:mx-auto pointer-events-auto min-h-screen flex flex-col items-center justify-center" style="max-width: min(960px, calc(100vw - 32px));">
        <div class="flex flex-col bg-white border shadow-sm rounded-md border-border-color w-full max-h-[92vh] overflow-hidden">
        <form method="POST" action="{{ $action }}" class="flex flex-col flex-1 min-h-0 overflow-hidden">
            @csrf
            @if ($method === 'PUT') @method('PUT') @endif

            <div class="flex justify-between items-center p-4 border-b border-border-color">
                <h2 class="text-base font-bold text-title">{{ $title }}</h2>
                <button type="button" class="size-7 inline-flex justify-center items-center rounded-md border border-border-color hover:bg-light cursor-pointer" data-hs-overlay="#{{ $id }}" aria-label="{{ __('Cancel') }}"><i class="ph ph-x text-sm"></i></button>
            </div>

            <div class="p-4 border-b border-border-color">
                <nav class="flex flex-wrap gap-1" data-product-tabs="{{ $id }}">
                    @foreach (['general' => ['General Info', 'ph-package'], 'inventory' => ['Inventory & Stock', 'ph-warehouse'], 'descriptions' => ['Description & Customs', 'ph-file-text']] as $tab => [$label, $icon])
                        <button type="button" data-product-tab-btn="{{ $tab }}"
                            class="btn-sm border border-border-color inline-flex items-center gap-2 cursor-pointer transition-colors {{ $tab === 'general' ? 'is-active bg-dark text-white border-dark' : 'bg-white text-title hover:bg-light' }}">
                            <i class="ph {{ $icon }}"></i> {{ __($label) }}
                        </button>
                    @endforeach
                </nav>
            </div>

            <div class="p-4 overflow-y-auto" data-product-panels="{{ $id }}">
                {{-- 1) Genel Bilgiler --}}
                <div data-product-tab-panel="general">
                    <div class="bg-white border border-border-color rounded-md overflow-hidden">
                        <table class="w-full text-sm">
                            <tbody>
                                <tr class="border-b border-border-color">
                                    <th class="text-left align-top py-2 px-3 bg-light w-40 font-semibold text-gray-900">{{ __('Name') }} <span class="text-danger">*</span></th>
                                    <td class="py-2 px-3">
                                        <input type="text" name="name" required value="{{ $product?->name }}" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                                    </td>
                                </tr>
                                <tr class="border-b border-border-color">
                                    <th class="text-left align-top py-2 px-3 bg-light w-40 font-semibold text-gray-900">SKU</th>
                                    <td class="py-2 px-3">
                                        <input type="text" name="sku" value="{{ $product?->sku }}" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0 font-mono">
                                    </td>
                                </tr>
                                <tr class="border-b border-border-color">
                                    <th class="text-left align-top py-2 px-3 bg-light w-40 font-semibold text-gray-900">{{ __('Barcode') }}</th>
                                    <td class="py-2 px-3">
                                        <div class="flex">
                                            <input type="text" name="barcode" data-barcode-input value="{{ $product?->barcode }}" placeholder="{{ __('EAN-13, UPC vs.') }}" class="w-full px-3 py-2 text-sm border border-border-color rounded-l-md bg-white focus:outline-none focus:ring-0 font-mono">
                                            <div class="hs-dropdown [--placement:bottom-right] [--auto-close:inside] relative inline-flex">
                                                <button type="button" class="hs-dropdown-toggle h-full px-3 border border-l-0 border-border-color rounded-r-md bg-light hover:bg-white cursor-pointer inline-flex items-center gap-1 text-default" title="{{ __('Auto-generate') }}">
                                                    <i class="ph ph-magic-wand"></i><i class="ph ph-caret-down text-xs"></i>
                                                </button>
                                                <div class="hs-dropdown-menu transition-[opacity,margin] duration hs-dropdown-open:opacity-100 opacity-0 hidden min-w-40 bg-white border border-border-color shadow rounded-md mt-2 z-10">
                                                    <div class="p-2 space-y-1">
                                                        <button type="button" data-barcode-gen="ean13" class="w-full text-left flex items-center gap-2 px-2 py-1.5 rounded-md text-sm text-gray-900 hover:bg-light"><i class="ph ph-barcode"></i> EAN-13</button>
                                                        <button type="button" data-barcode-gen="upca" class="w-full text-left flex items-center gap-2 px-2 py-1.5 rounded-md text-sm text-gray-900 hover:bg-light"><i class="ph ph-barcode"></i> UPC-A</button>
                                                        <button type="button" data-barcode-gen="ean8" class="w-full text-left flex items-center gap-2 px-2 py-1.5 rounded-md text-sm text-gray-900 hover:bg-light"><i class="ph ph-barcode"></i> EAN-8</button>
                                                        <button type="button" data-barcode-gen="code128" class="w-full text-left flex items-center gap-2 px-2 py-1.5 rounded-md text-sm text-gray-900 hover:bg-light"><i class="ph ph-barcode"></i> CODE 128</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <tr class="border-b border-border-color">
                                    <th class="text-left align-top py-2 px-3 bg-light w-40 font-semibold text-gray-900">{{ __('List Price') }}</th>
                                    <td class="py-2 px-3">
                                        <input type="number" name="list_price" step="0.0001" min="0" value="{{ $product?->list_price ?? '0' }}" class="w-56 px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                                    </td>
                                </tr>
                                <tr class="border-b border-border-color">
                                    <th class="text-left align-top py-2 px-3 bg-light w-40 font-semibold text-gray-900">{{ __('Unit') }} <span class="text-danger">*</span></th>
                                    <td class="py-2 px-3">
                                        <select name="uom_id" required class="w-56 px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                                            @foreach ($uoms as $uom)
                                                <option value="{{ $uom->id }}" @selected($product?->uom_id === $uom->id)>{{ $uom->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                </tr>
                                <tr class="border-b border-border-color">
                                    <th class="text-left align-top py-2 px-3 bg-light w-40 font-semibold text-gray-900">{{ __('Category') }}</th>
                                    <td class="py-2 px-3">
                                        <select name="product_category_id" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                                            <option value="">—</option>
                                            @foreach ($categories as $category)
                                                <option value="{{ $category->id }}" @selected($product?->product_category_id === $category->id)>{{ $category->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th class="text-left align-top py-2 px-3 bg-light w-40 font-semibold text-gray-900">{{ __('Brand') }}</th>
                                    <td class="py-2 px-3">
                                        <select name="product_brand_id" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                                            <option value="">—</option>
                                            @foreach ($brands as $brand)
                                                <option value="{{ $brand->id }}" @selected($product?->product_brand_id === $brand->id)>{{ $brand->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- 2) Envanter & Stok --}}
                <div data-product-tab-panel="inventory" class="hidden">
                    <div class="bg-white border border-border-color rounded-md overflow-hidden">
                        <table class="w-full text-sm">
                            <tbody>
                                <tr class="border-b border-border-color">
                                    <th class="text-left align-top py-2 px-3 bg-light w-52 font-semibold text-gray-900">{{ __('Type') }}</th>
                                    <td class="py-2 px-3">
                                        <select name="product_type" class="w-56 px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                                            @foreach (['stockable', 'consumable', 'service'] as $type)
                                                <option value="{{ $type }}" @selected(($product?->product_type ?? 'stockable') === $type)>{{ __('product-type.'.$type) }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                </tr>
                                <tr class="border-b border-border-color">
                                    <th class="text-left align-top py-2 px-3 bg-light w-52 font-semibold text-gray-900">{{ __('Tracking') }}</th>
                                    <td class="py-2 px-3">
                                        <select name="track_by" class="w-56 px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                                            <option value="none" @selected(($product?->track_by ?? 'none') === 'none')>{{ __('None') }}</option>
                                            <option value="lot" @selected($product?->track_by === 'lot')>{{ __('Lot') }}</option>
                                            <option value="serial" @selected($product?->track_by === 'serial')>{{ __('Serial') }}</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr class="border-b border-border-color">
                                    <th class="text-left align-top py-2 px-3 bg-light w-52 font-semibold text-gray-900">{{ __('Reservation Method') }}</th>
                                    <td class="py-2 px-3">
                                        <select name="reservation_method" class="w-56 px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                                            <option value="at_confirmation" @selected(($product?->reservation_method ?? 'at_confirmation') === 'at_confirmation')>{{ __('reservation.at_confirmation') }}</option>
                                            <option value="manual" @selected($product?->reservation_method === 'manual')>{{ __('reservation.manual') }}</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr class="border-b border-border-color">
                                    <th class="text-left align-top py-2 px-3 bg-light w-52 font-semibold text-gray-900">{{ __('Can be sold') }}</th>
                                    <td class="py-2 px-3">
                                        <input type="hidden" name="sale_ok" value="0">
                                        <label class="inline-flex items-center gap-2 text-sm">
                                            <input type="checkbox" id="sale_ok_{{ $id }}" name="sale_ok" value="1" class="size-4 rounded border-border-color" @checked($product?->sale_ok ?? true)>
                                            <span class="text-default">{{ __('Yes — appears in sales flow') }}</span>
                                        </label>
                                    </td>
                                </tr>
                                <tr class="border-b border-border-color">
                                    <th class="text-left align-top py-2 px-3 bg-light w-52 font-semibold text-gray-900">{{ __('Can be purchased') }}</th>
                                    <td class="py-2 px-3">
                                        <input type="hidden" name="purchase_ok" value="0">
                                        <label class="inline-flex items-center gap-2 text-sm">
                                            <input type="checkbox" id="purchase_ok_{{ $id }}" name="purchase_ok" value="1" class="size-4 rounded border-border-color" @checked($product?->purchase_ok ?? true)>
                                            <span class="text-default">{{ __('Yes — appears in purchase flow') }}</span>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th class="text-left align-top py-2 px-3 bg-light w-52 font-semibold text-gray-900">{{ __('Kit / bundle') }}</th>
                                    <td class="py-2 px-3">
                                        <input type="hidden" name="is_kit" value="0">
                                        <label class="inline-flex items-center gap-2 text-sm">
                                            <input type="checkbox" id="is_kit_{{ $id }}" name="is_kit" value="1" class="size-4 rounded border-border-color" @checked($product?->is_kit)>
                                            <span class="text-default">{{ __('Assemble other products into a kit') }}</span>
                                        </label>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- 3) Açıklama & Gümrük --}}
                <div data-product-tab-panel="descriptions" class="hidden">
                    <div class="bg-white border border-border-color rounded-md overflow-hidden">
                        <table class="w-full text-sm">
                            <tbody>
                                <tr class="border-b border-border-color">
                                    <th class="text-left align-top py-2 px-3 bg-light w-52 font-semibold text-gray-900">{{ __('Internal Note') }}</th>
                                    <td class="py-2 px-3">
                                        <textarea name="description" rows="3" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">{{ $product?->description }}</textarea>
                                    </td>
                                </tr>
                                <tr class="border-b border-border-color">
                                    <th class="text-left align-top py-2 px-3 bg-light w-52 font-semibold text-gray-900">{{ __('Customer Description') }}</th>
                                    <td class="py-2 px-3">
                                        <textarea name="description_sale" rows="3" placeholder="{{ __('Shown on quotations and invoices') }}" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">{{ $product?->description_sale }}</textarea>
                                    </td>
                                </tr>
                                <tr class="border-b border-border-color">
                                    <th class="text-left align-top py-2 px-3 bg-light w-52 font-semibold text-gray-900">{{ __('HS Code') }}</th>
                                    <td class="py-2 px-3">
                                        <input type="text" name="hs_code" value="{{ $product?->hs_code }}" placeholder="8471.30.00" maxlength="20" class="w-56 px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0 font-mono">
                                    </td>
                                </tr>
                                <tr>
                                    <th class="text-left align-top py-2 px-3 bg-light w-52 font-semibold text-gray-900">{{ __('Country of Origin') }}</th>
                                    <td class="py-2 px-3">
                                        <input type="text" name="country_of_origin" value="{{ $product?->country_of_origin }}" placeholder="TR" maxlength="2" class="w-24 px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0 uppercase font-mono">
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2 p-4 border-t border-border-color">
                <button type="button" class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light cursor-pointer" data-hs-overlay="#{{ $id }}">{{ __('Cancel') }}</button>
                <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Save') }}</button>
            </div>
        </form>

        @if ($product !== null)
            <div class="border-t border-border-color p-4 bg-light/40">
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
</div>
<script>
(function () {
    const modal = document.getElementById('{{ $id }}');
    if (!modal || modal.dataset.productModalWired === '1') return;
    modal.dataset.productModalWired = '1';

    // --- Tabs ---
    const tablist = modal.querySelector('[data-product-tabs="{{ $id }}"]');
    const panels = modal.querySelector('[data-product-panels="{{ $id }}"]');
    if (tablist && panels) {
        tablist.querySelectorAll('[data-product-tab-btn]').forEach(btn => {
            btn.addEventListener('click', () => {
                const target = btn.dataset.productTabBtn;
                tablist.querySelectorAll('[data-product-tab-btn]').forEach(b => {
                    const active = b.dataset.productTabBtn === target;
                    b.classList.toggle('is-active', active);
                    b.classList.toggle('bg-dark', active);
                    b.classList.toggle('text-white', active);
                    b.classList.toggle('border-dark', active);
                    b.classList.toggle('bg-white', !active);
                    b.classList.toggle('text-title', !active);
                    b.classList.toggle('hover:bg-light', !active);
                });
                panels.querySelectorAll('[data-product-tab-panel]').forEach(p => {
                    p.classList.toggle('hidden', p.dataset.productTabPanel !== target);
                });
            });
        });
    }

    // --- Barcode generator ---
    const input = modal.querySelector('[data-barcode-input]');
    if (input) {
        const digits = (n) => Array.from({length: n}, () => Math.floor(Math.random() * 10)).join('');
        // GS1 mod-10 check digit — right-to-left, alternating 3/1 weights.
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
    }
})();
</script>
