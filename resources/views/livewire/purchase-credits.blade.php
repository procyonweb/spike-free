@php
    use Opcodes\Spike\Facades\Spike;
    use Opcodes\Spike\PaymentProvider;
@endphp
<div class="bg-white shadow overflow-hidden sm:rounded-md"
     @if($payNow && $cart->notEmpty() && \Opcodes\Spike\Facades\Spike::paymentProvider()->isStripe())
         x-init="$wire.checkout()"
     @endif
>
    <div class="bg-white px-4 py-5 border-b border-gray-200 sm:px-6 md:flex md:items-center">
        <div>
            <h3 class="text-lg leading-6 font-medium text-gray-900">{{ __('spike::translations.products') }}</h3>
            <p class="mt-1 text-sm text-gray-600">
                {{ __('spike::translations.products_description') }}
            </p>
            @isset($errorMessage)
                <p class="text-red-700 mt-1 px-3 py-2 bg-red-50 border border-red-200 rounded-lg text-sm" role="alert">
                    {{ $errorMessage }}
                </p>
            @endif
        </div>
        <div class="md:flex-shrink-0 mt-2 md:mt-0 {{ $cart->items->count() > 0 ? 'flex' : 'hidden' }} flex-col items-end text-sm text-gray-600 ml-10">
            @if(Spike::paymentProvider()->isStripe())
                <button wire:click="checkout"
                        wire:target="checkout"
                        wire:loading.attr="disabled"
                        wire:loading.class="opacity-50"
                        class="flex items-center bg-brand hover:opacity-80 text-white rounded-md shadow px-3 py-2 text-sm"
                >
                    <x-spike::shared.spinner class="size-5 mr-2" wire:loading wire:target="checkout"/>
                    <x-spike::icons.cart class="size-5 mr-2" wire:loading.remove wire:target="checkout"/>
                    {{ __('spike::translations.checkout') }}
                </button>
            @elseif(Spike::paymentProvider()->isPaddle())
                <livewire:spike::product-checkout-button-paddle key="{{ now() }}" />
            @endif

            <div class="flex items-center text-base mt-2">
                {!! __('spike::translations.cart_total', [
                    'price' => '<strong class="ml-1">'.$cart->totalPriceFormatted().'</strong>',
                ]) !!}
            </div>
        </div>
    </div>

    <ul role="list" class="divide-y divide-gray-200">
        @php /** @var \Opcodes\Spike\Product $product */ @endphp
        @foreach($products as $product)
            <li>
                <div class="w-full">
                    <div class="px-4 py-4 flex items-center sm:px-6">
                        <div class="min-w-0 flex-1 sm:flex sm:items-center sm:justify-between">
                            <div class="space-y-1">
                                <x-spike::shop.product-heading :product="$product" class="mb-2" />

                                @foreach($product->provides as $providable)
                                    <x-spike::shop.providable-item :providable="$providable" />
                                @endforeach

                                @foreach($product->features as $feature)
                                    <x-spike::shop.feature-item :feature="$feature" />
                                @endforeach
                            </div>

                            <div class="mt-4 flex-shrink-0 sm:mt-0 sm:ml-5">
                                <x-spike::shop.product-price :product="$product" />
                            </div>
                        </div>

                        <x-spike::shop.product-quantity-buttons
                            class="md:ml-5"
                            :cart="$cart"
                            :product="$product"
                        />
                    </div>
                </div>
            </li>
        @endforeach
    </ul>

    @if($payNow && $cart->notEmpty() && \Opcodes\Spike\Facades\Spike::paymentProvider()->isPaddle())
         <script>
            document.addEventListener('DOMContentLoaded', function () {
                setTimeout(function () {
                    var button = document.getElementById('paddle-button-checkout');
                    if (button) {
                        button.click();
                    }
                }, 500);
            });
         </script>
     @endif
</div>
