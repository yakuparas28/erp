@extends('app.layouts.app')

@section('title', $template->name)

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-3 lg:mb-6">
    <h1 class="text-gray-900 text-xl font-bold mb-0">{{ $template->name }}</h1>
    <a href="{{ route('app.inventory.templates.index') }}" class="btn-sm bg-white border border-border-color text-gray-900 inline-flex items-center gap-2 hover:bg-light">
        <i class="ph ph-arrow-left"></i> {{ __('Back to List') }}
    </a>
</div>

<div class="grid grid-cols-12 gap-4">
    <div class="col-span-12 lg:col-span-5">
        <div class="bg-white border border-border-color rounded-md p-4">
            <h2 class="text-base font-bold text-title mb-3">{{ __('Attributes') }}</h2>
            <ul class="space-y-2 mb-4">
                @forelse ($template->attributeLines as $line)
                    <li class="text-sm text-default flex items-center justify-between">
                        <span>{{ $line->attribute->name }}</span>
                        <span class="text-[11px] bg-light text-default px-2 py-0.5 rounded">{{ __('creation-mode.'.$line->attribute->creation_mode) }}</span>
                    </li>
                @empty
                    <li class="text-sm text-default">{{ __('No attributes attached yet.') }}</li>
                @endforelse
            </ul>
            @if ($availableAttributes->isNotEmpty())
                <form method="POST" action="{{ route('app.inventory.templates.attributes.attach', $template) }}" class="flex flex-wrap items-end gap-2">
                    @csrf
                    <div class="flex-1 min-w-40">
                        <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Attach Attribute') }}</label>
                        <select name="product_attribute_id" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                            @foreach ($availableAttributes as $attribute)
                                <option value="{{ $attribute->id }}">{{ $attribute->name }} ({{ __('creation-mode.'.$attribute->creation_mode) }})</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Attach') }}</button>
                </form>
            @endif
        </div>
    </div>

    <div class="col-span-12 lg:col-span-7">
        <div class="bg-white border border-border-color rounded-md p-4">
            <h2 class="text-base font-bold text-title mb-3">{{ __('Generated Variants') }}</h2>
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-sm text-default border-b border-border-color">
                        <th class="text-left py-2 font-semibold text-gray-900">{{ __('Name') }}</th>
                        <th class="text-left py-2 font-semibold text-gray-900">SKU</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($template->variants as $variant)
                        <tr class="border-b border-border-color">
                            <td class="py-2 text-sm text-title">{{ $variant->name }}</td>
                            <td class="py-2 text-sm text-default">{{ $variant->sku ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="py-4 text-center text-sm text-default">{{ __('No variants generated yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
