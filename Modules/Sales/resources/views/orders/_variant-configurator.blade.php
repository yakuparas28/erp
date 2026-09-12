<div id="variant-configurator-modal" class="hs-overlay hidden size-full fixed top-0 start-0 z-[80] overflow-x-hidden overflow-y-auto pointer-events-none">
    <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-2xl sm:w-full m-3 sm:mx-auto pointer-events-auto min-h-screen flex items-center justify-center">
        <form method="POST" action="{{ route('app.sales.orders.lines.configure', $so) }}" class="flex flex-col bg-white border shadow-sm rounded-md border-border-color w-full">
            @csrf
            <div class="flex justify-between items-center p-4 border-b border-border-color">
                <h2 class="text-base font-bold text-title">{{ __('Configure Variant') }}</h2>
                <button type="button" class="size-7 inline-flex justify-center items-center rounded-md border border-border-color hover:bg-light cursor-pointer" data-hs-overlay="#variant-configurator-modal" aria-label="{{ __('Cancel') }}"><i class="ph ph-x text-sm"></i></button>
            </div>
            <div class="p-4 grid grid-cols-12 gap-3">
                <div class="col-span-12">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Template') }} <span class="text-danger">*</span></label>
                    <select id="configurator-template-select" name="product_template_id" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                        <option value="">{{ __('— Select template') }}</option>
                        @foreach ($templates as $template)
                            <option value="{{ $template->id }}">{{ $template->name }}</option>
                        @endforeach
                    </select>
                </div>

                @foreach ($templates as $template)
                    <div class="col-span-12 configurator-template-section hidden" data-template-id="{{ $template->id }}">
                        <div class="border border-border-color rounded-md p-3 space-y-3">
                            @foreach ($template->attributeLines as $line)
                                @php $attribute = $line->attribute; @endphp
                                <div>
                                    <label class="text-sm font-semibold text-gray-900 mb-2 block">{{ $attribute->name }} <span class="text-danger">*</span></label>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach ($attribute->values as $value)
                                            <label class="cursor-pointer">
                                                <input type="radio" name="attribute_value_ids[{{ $attribute->id }}]" value="{{ $value->id }}" data-value-id="{{ $value->id }}" data-attribute-id="{{ $attribute->id }}" required class="peer sr-only">
                                                <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full border border-border-color bg-white text-sm peer-checked:bg-dark peer-checked:text-white peer-checked:border-dark hover:bg-light">
                                                    @if ($attribute->display_type === 'color' && $value->html_color)
                                                        <span class="size-4 rounded-full border border-white/40" style="background: {{ $value->html_color }}"></span>
                                                    @elseif ($value->image_path)
                                                        <img src="{{ asset('storage/'.$value->image_path) }}" alt="" class="size-5 rounded object-cover">
                                                    @endif
                                                    {{ $value->value }}
                                                    @if ((float) $value->price_extra !== 0.0)
                                                        <span class="text-xs opacity-70">(+{{ $value->price_extra }})</span>
                                                    @endif
                                                </span>
                                                @if ($value->is_custom)
                                                    <input type="text" name="custom_values[{{ $value->id }}]" placeholder="{{ __('Your custom text…') }}" class="mt-1 w-full px-2 py-1 text-xs border border-border-color rounded-md bg-white focus:outline-none focus:ring-0 hidden custom-input-for-{{ $value->id }}">
                                                @endif
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                <div class="col-span-6 sm:col-span-4">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Unit') }} <span class="text-danger">*</span></label>
                    <select name="uom_id" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                        @foreach ($uomOptions as $uom)
                            <option value="{{ $uom->id }}">{{ $uom->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-span-6 sm:col-span-4">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Quantity') }} <span class="text-danger">*</span></label>
                    <input type="number" name="qty" step="0.0001" min="0.0001" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                </div>
                <div class="col-span-12 sm:col-span-4">
                    <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Unit Price') }} <span class="text-danger">*</span></label>
                    <input type="number" name="unit_price" step="0.0001" min="0" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                </div>
            </div>
            <div class="flex justify-end gap-2 p-4 border-t border-border-color">
                <button type="button" class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light cursor-pointer" data-hs-overlay="#variant-configurator-modal">{{ __('Cancel') }}</button>
                <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Add to Order') }}</button>
            </div>
        </form>
    </div>
</div>

<script>
    (function () {
        var templateSelect = document.getElementById('configurator-template-select');
        var sections = document.querySelectorAll('.configurator-template-section');
        if (!templateSelect || sections.length === 0) return;

        // attribute_value_ids is submitted as array; we use dict[attribute_id]=value_id
        // Laravel accepts both — but the controller expects a numeric array; convert on submit.
        templateSelect.addEventListener('change', function () {
            var selectedId = templateSelect.value;
            sections.forEach(function (section) {
                if (section.getAttribute('data-template-id') === selectedId) {
                    section.classList.remove('hidden');
                    section.querySelectorAll('input[type=radio]').forEach(function (r) { r.required = true; });
                } else {
                    section.classList.add('hidden');
                    section.querySelectorAll('input[type=radio]').forEach(function (r) {
                        r.required = false;
                        r.checked = false;
                    });
                }
            });
        });

        // Show custom input when a is_custom value is selected
        document.querySelectorAll('input[type=radio][data-value-id]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                var valueId = radio.getAttribute('data-value-id');
                document.querySelectorAll('.custom-input-for-' + valueId).forEach(function (input) {
                    input.classList.remove('hidden');
                });
            });
        });

        // Rewrite dict form to array on submit
        var form = templateSelect.closest('form');
        form.addEventListener('submit', function () {
            var selectedRadios = form.querySelectorAll('.configurator-template-section:not(.hidden) input[type=radio]:checked');
            selectedRadios.forEach(function (radio, index) {
                var hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'attribute_value_ids[]';
                hidden.value = radio.value;
                form.appendChild(hidden);
                radio.name = '_disabled_' + radio.name;
            });
        });
    })();
</script>
