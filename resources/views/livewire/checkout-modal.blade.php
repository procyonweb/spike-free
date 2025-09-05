<form class="w-full relative flex flex-col bg-white pt-6 pb-8 overflow-hidden sm:pb-6 sm:rounded-lg lg:py-8" x-data
      x-init="$wire.set('loadPaymentMethod', true)">
    <div class="flex items-center justify-between px-4 sm:px-6 lg:px-8">
        <h2 class="text-lg font-medium text-gray-900">{{ __('spike::translations.checkout') }}</h2>
        <button type="button" class="text-gray-400 hover:text-gray-500"
                wire:click="$dispatch('closeModal')"
                wire:loading.attr="disabled"
                wire:loading.class="opacity-50"
                wire:target="pay"
        >
            <span class="sr-only">{{ __('spike::translations.close_modal') }}</span>
            <x-spike::icons.dismiss class="size-6"/>
        </button>
    </div>

    @if(!app()->environment('production') && $cart->items->contains(fn($item) => empty($item->product()->payment_provider_price_id)))
        <div class="mt-4 px-4 sm:px-6 lg:px-8">
            <div class="px-3 py-2 text-sm bg-red-100 text-red-900 rounded-md">
                One or more products in the cart have an empty <span
                        class="font-mono font-semibold">`payment_provider_price_id`</span> and the transaction might not work.
                <a href="https://spike.opcodes.io/docs/configuring-spike/products#how-to-set-up-stripe-products"
                   target="_blank" class="underline">Read more here</a>
            </div>
        </div>
    @endif

    <section aria-labelledby="cart-heading">
        <h2 id="cart-heading" class="sr-only">{{ __('spike::translations.credits_in_cart') }}</h2>

        <ul role="list" class="divide-y divide-gray-200 px-4 sm:px-6 lg:px-8">
            @foreach($cart->items as $item)
                <li class="py-8 flex text-sm sm:items-center">
                    <div class="flex-auto grid gap-y-3 gap-x-5 grid-rows-1 grid-cols-1 items-start sm:flex sm:gap-0 sm:items-center">
                        <div class="flex-auto row-end-1 sm:pr-6">
                            <h3 class="font-medium text-gray-900">
                                {{ $item->product()->name }}
                                <span class="ml-2 text-gray-600">x {{ $item->quantity }}</span>
                            </h3>
                            <p class="mt-1 text-gray-600 flex flex-col items-start">
                                @foreach($item->totalProvides() as $provide)
                                    <span class="inline-flex items-center mr-5">
                                <x-spike::shared.providable-icon :providable="$provide" class="size-4 mr-1"/>
                                {{ $provide->toString() }}
                            </span>
                                @endforeach
                            </p>
                        </div>
                        <p class="row-end-2 row-span-2 font-medium text-gray-900 sm:ml-6 sm:order-1 sm:flex-none sm:w-1/4 sm:text-right">
                            {{ $item->totalPriceFormatted() }}
                        </p>
                        <div class="flex items-center sm:flex-none sm:block sm:text-center">
                            <button type="button"
                                    wire:click="removeProductCompletely('{{ $item->product_id }}')"
                                    wire:loading.attr="disabled"
                                    wire:loading.class="opacity-50"
                                    wire:target="pay"
                                    class="ml-4 font-medium text-brand hover:opacity-80 sm:ml-0">
                                <span>{{ __('spike::translations.remove') }}</span>
                            </button>
                        </div>
                    </div>
                </li>
            @endforeach

            @if($cart->empty())
                <li class="py-8 flex text-sm sm:items-center justify-center">
                    {{ __('spike::translations.empty_cart_description') }}
                </li>
            @endif
        </ul>
    </section>

    <section aria-labelledby="summary-heading" class="mt-auto sm:px-6 lg:px-8">
        <div class="bg-gray-50 p-6 sm:p-8 sm:rounded-lg">
            <h2 id="summary-heading" class="sr-only">{{ __('spike::translations.order_summary') }}</h2>

            <div class="flow-root">
                <dl class="-my-4 text-sm divide-y divide-gray-200">
                    @if($cart->hasPromotionCode())
                        <div class="py-4 flex items-center justify-between">
                            <dt class="text-gray-600">
                                {{ __('spike::translations.discount_code') }}
                                <a href="#" wire:click.prevent="removeDiscountCode"
                                   class="ml-2 text-brand font-semibold hover:opacity-80">{{ __('spike::translations.remove') }}</a>
                            </dt>
                            <dd class="font-medium text-gray-900 flex items-center">
                                <x-spike::icons.ticket-diagonal class="size-4 text-gray-400 mr-1"/>
                                {{ $cart->promotionCode()->coupon()?->name }}
                            </dd>
                        </div>
                    @endif
                    <div class="py-4 flex items-center justify-between">
                        <dt class="text-base font-medium text-gray-900">{{ __('spike::translations.order_total') }}</dt>
                        <dd class="text-base font-medium text-gray-900">
                            @if($cart->hasPromotionCode())
                                <span class="line-through mr-1 text-gray-400">{{ $cart->totalPriceFormatted() }}</span>
                                <span>{{ $cart->totalPriceAfterDiscountFormatted() }}</span>
                            @else
                                {{ $cart->totalPriceFormatted() }}
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>
        </div>
    </section>

    @if(\Opcodes\Spike\Facades\Spike::stripeAllowDiscounts() && !$cart->hasPromotionCode())
        <div class="px-4 sm:px-6 lg:px-8 mt-4 flex flex-col items-end justify-end" x-data="{ discountCode: '' }">
            <div class="flex">
                <div>
                    <label for="discount-code" class="block sr-only sm:hidden text-gray-700 text-sm">
                        {{ __('spike::translations.discount_code') }}
                    </label>
                    <div class="relative">
                        <input type="text" name="discount-code" id="discount-code"
                               class="pl-10 shadow-sm block w-full border-gray-200 rounded-l-md text-sm"
                               placeholder="{{ __('spike::translations.discount_code_placeholder') }}"
                               x-model="discountCode"
                               x-on:keydown.enter.prevent="$wire.addDiscountCode(discountCode)"
                        >
                        <x-spike::icons.ticket-diagonal
                                class="absolute left-[0.80rem] top-[0.5rem] size-5 text-gray-300"/>
                    </div>
                </div>
                <div class="focus-within:z-10">
                    <button type="button"
                            class="flex-shrink-0 inline-flex items-center px-4 py-2 text-sm font-medium shadow-sm rounded-r-md border border-gray-200 bg-gray-100 border-l-transparent hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-600"
                            x-on:click.prevent="$wire.addDiscountCode(discountCode)"
                    >
                        <x-spike::shared.spinner class="size-4 mr-2" wire:loading wire:target="addDiscountCode"/>
                        {{ __('spike::translations.apply') }}
                    </button>
                </div>
            </div>
            @if($discount_code_error)
                <div class="text-red-600 text-sm">{{ $discount_code_error }}</div>
            @endif
        </div>
    @endif

    <div class="mt-8 lg:mt-12 flex flex-col sm:flex-row sm:justify-end space-y-3 sm:space-y-0 sm:space-x-3 px-4 sm:px-6 lg:px-8">
        <div class="flex items-center bg-gray-50 border border-transparent rounded-md py-2 px-4 text-sm font-medium text-gray-600">
            @if(isset($paymentMethod))
                @if($paymentMethod->type === 'card')
                {!! __('spike::translations.using_payment_card', ['last_four' => '&bull;&bull;&bull;&bull; '.$paymentMethod->card->last4]) !!}
                @else
                {!! __('spike::translations.using_payment_method', ['type' => \Opcodes\Spike\Utils::paymentMethodName($paymentMethod)]) !!}
                @endif
            @elseif(!$loadPaymentMethod)
                {{ __('spike::translations.loading') }}
            @else
                <a href="#" wire:click="$dispatch('openModal', { component: 'spike::add-payment-method' })"
                   class="text-brand font-semibold hover:opacity-80"
                >{{ __('spike::translations.setup_payment_card') }}</a>
            @endif
        </div>
        <button type="submit"
                class="@if(!$paymentMethod || $cart->empty()) opacity-50 @else hover:opacity-80 @endif flex items-center justify-center bg-brand border border-transparent rounded-md shadow-sm py-3 sm:py-2 px-4 text-sm font-medium text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-gray-50 focus:ring-brand"
                @if(!$paymentMethod || $cart->empty()) disabled @endif
                wire:click.prevent="pay"
                wire:loading.attr="disabled"
                wire:loading.class="opacity-50"
        >
            <x-spike::icons.checkmark-lock class="size-4 mr-2" wire:loading.remove wire:target="pay"/>
            <x-spike::shared.spinner class="size-4 mr-2" wire:loading wire:target="pay"/>
            {{ __('spike::translations.pay') }}
        </button>
    </div>
</form>
