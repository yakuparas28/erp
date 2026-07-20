<div id="kit-components-modal-{{ $product->id }}" class="hs-overlay hidden size-full fixed top-0 start-0 z-[80] overflow-x-hidden overflow-y-auto pointer-events-none">
    <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-xl sm:w-full m-3 sm:mx-auto pointer-events-auto min-h-screen flex items-center justify-center">
        <div class="flex flex-col bg-white border shadow-sm rounded-md border-border-color w-full">
            <div class="flex justify-between items-center p-4 border-b border-border-color">
                <h2 class="text-base font-bold text-title">{{ __('Kit Components') }} — {{ $product->name }}</h2>
                <button type="button" class="size-7 inline-flex justify-center items-center rounded-md border border-border-color hover:bg-light cursor-pointer" data-hs-overlay="#kit-components-modal-{{ $product->id }}" aria-label="{{ __('Cancel') }}"><i class="ph ph-x text-sm"></i></button>
            </div>
            <div class="p-4">
                <table class="w-full text-sm mb-4">
                    <thead>
                        <tr class="text-sm text-default border-b border-border-color">
                            <th class="text-left py-2 font-semibold text-gray-900">{{ __('Component') }}</th>
                            <th class="text-left py-2 font-semibold text-gray-900">{{ __('Quantity') }}</th>
                            <th class="text-left py-2 font-semibold text-gray-900"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($product->kitComponents as $component)
                            <tr class="border-b border-border-color">
                                <td class="py-2 text-sm text-title">{{ $component->componentProduct->name }}</td>
                                <td class="py-2 text-sm text-default">{{ $component->qty }}</td>
                                <td class="py-2 text-right">
                                    <form method="POST" action="{{ route('app.inventory.kit-components.destroy', $component) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-danger text-sm cursor-pointer">{{ __('Remove') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="py-4 text-center text-sm text-default">{{ __('No components yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <form method="POST" action="{{ route('app.inventory.kit-components.store', $product) }}" class="flex flex-wrap items-end gap-3">
                    @csrf
                    <div class="flex-1 min-w-40">
                        <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Component') }}</label>
                        <select name="component_product_id" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                            @foreach ($nonKitProducts as $componentOption)
                                @if ($componentOption->id !== $product->id)
                                    <option value="{{ $componentOption->id }}">{{ $componentOption->name }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <div class="w-28">
                        <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Quantity') }}</label>
                        <input type="number" step="0.0001" min="0.0001" name="qty" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                    </div>
                    <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Add') }}</button>
                </form>
            </div>
        </div>
    </div>
</div>
