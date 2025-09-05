@props(['cart', 'product'])
<div {{ $attributes->merge(['class' => 'min-w-[130px] flex justify-end']) }}>
    @if(!$cart->hasProduct($product->id))
        <button
            class="text-sm font-medium flex-shrink-0 flex items-center rounded-md px-4 py-2 text-white bg-brand hover:opacity-80"
            wire:click="addProduct('{{ $product->id }}')"
            wire:loading.attr="disabled"
            wire:loading.class="opacity-50"
        >
            <x-spike::icons.add class="size-4 mr-2" />
            {{ __('spike::translations.add_to_cart') }}
        </button>
    @else
        <div class="flex rounded-md">
            <button type="button"
                    class="-ml-px relative inline-flex items-center space-x-2 px-2 py-2 border border-gray-300 text-sm font-medium rounded-l-md text-gray-700 bg-gray-50 hover:bg-gray-100 focus:outline-none focus:ring-1 focus:ring-brand focus:border-brand"
                    wire:click="removeProduct('{{ $product->id }}')"
            >
                <x-spike::icons.subtract class="size-5 text-gray-400"/>
                <span class="sr-only">{{ __('spike::translations.remove') }}</span>
            </button>
            <div
                class="px-4 border-b border-t focus:ring-brand focus:border-brand sm:text-sm border-gray-300 flex items-center justify-center">
                {{ $cart->items->where('product_id', $product->id)->first()?->quantity ?? 0 }}
            </div>
            <button type="button"
                    class="-ml-px relative inline-flex items-center space-x-2 px-2 py-2 border border-gray-300 text-sm font-medium rounded-r-md text-gray-700 bg-gray-50 hover:bg-gray-100 focus:outline-none focus:ring-1 focus:ring-brand focus:border-brand"
                    wire:click="addProduct('{{ $product->id }}')"
            >
                <x-spike::icons.add class="size-5 text-gray-400"/>
                <span class="sr-only">{{ __('spike::translations.add_to_cart') }}</span>
            </button>
        </div>
    @endif
</div>
