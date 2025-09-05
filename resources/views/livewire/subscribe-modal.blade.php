<form class="w-full relative flex flex-col bg-white pt-6 pb-8 overflow-hidden sm:pb-6 sm:rounded-lg lg:py-8"
    @if($isStripe)
    wire:init="loadPaymentMethod"
    @endif
>
    <div class="flex items-center justify-between px-4 sm:px-6 lg:px-8">
        @if($cancellationOfferAccepted)
        <h2 class="text-lg font-medium text-gray-900">{{ __('spike::translations.subscription_success') }}</h2>
        @else
        <h2 class="text-lg font-medium text-gray-900">{{ $plan->isFree() ? __('spike::translations.unsubscribe') : __('spike::translations.subscribe') }}</h2>
        @endif
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

    @if(!app()->environment('production') && empty($plan->payment_provider_price_id))
        <div class="mt-4 px-4 sm:px-6 lg:px-8">
            <div class="px-3 py-2 text-sm bg-red-100 text-red-900 rounded-md">
                The subscription plan's <span class="font-mono font-semibold">`payment_provider_price_id`</span> is empty and the
                transaction might not work.
                <a href="https://spike.opcodes.io/docs/configuring-spike/products#how-to-set-up-stripe-products"
                   target="_blank" class="underline">Read more here</a>
            </div>
        </div>
    @endif

    @if(empty($currentCancellationOffer) && ! $cancellationOfferAccepted)
    <section aria-labelledby="summary-heading" class="mt-8 sm:px-6 lg:px-8">
        <div class="bg-gray-50 p-6 sm:p-8 sm:rounded-lg">
            <h2 id="summary-heading" class="sr-only">{{ __('spike::translations.order_summary') }}</h2>

            <div class="flow-root">
                <dl class="-my-4 text-sm divide-y divide-gray-200">
                    @foreach($provideDifferences as $difference)
                        <div class="py-4 flex items-center justify-between">
                            <dt class="text-gray-600">{{ $difference['name'] }}</dt>
                            <dd class="font-medium text-gray-900 flex items-center">
                                @if($difference['old'])
                                    <div class="flex items-center line-through mr-3 text-gray-400">
                                        <x-spike::shared.providable-icon :providable="$difference['old']"
                                                                         class="size-4 mr-1"/>
                                        {{ $difference['old']->toString() }}
                                    </div>
                                @endif
                                @isset($difference['new'])
                                    <x-spike::shared.providable-icon :providable="$difference['new']"
                                                                     class="size-4 mr-1"/>
                                    {{ $difference['new']->toString() }}
                                @endisset
                            </dd>
                        </div>
                    @endforeach
                    @if($promotionCode)
                        <div class="py-4 flex items-center justify-between">
                            <dt class="text-gray-600">
                                {{ __('spike::translations.discount_code') }}
                                <a href="#" wire:click.prevent="removeDiscountCode"
                                   class="ml-2 text-brand font-semibold hover:opacity-80">{{ __('spike::translations.remove') }}</a>
                            </dt>
                            <dd class="font-medium text-gray-900 flex items-center">
                                <x-spike::icons.ticket-diagonal class="size-4 text-gray-400 mr-1"/>
                                {{ $promotionCode->coupon()?->name }}
                            </dd>
                        </div>
                    @endif
                    <div class="py-4 flex items-center justify-between">
                        <dt class="text-base font-medium text-gray-900">{{ __('spike::translations.total_price') }}</dt>
                        <dd class="text-base font-medium text-gray-900 text-right">
                            @if($currentPlan)
                                <span class="line-through mr-3 text-gray-400"
                                >@if($currentPlan->period !== $plan->period)
                                        {{ $currentPlan->isYearly()
                                            ? __('spike::translations.price_per_year', ['price' => $currentPlan->priceFormatted()])
                                            : __('spike::translations.price_per_month', ['price' => $currentPlan->priceFormatted()])
                                        }}
                                    @else
                                        {{ $currentPlan->priceFormatted() }}
                                    @endif</span>
                            @endif
                            {{ $plan->priceAfterDiscountFormatted() }}
                            <p class="text-sm text-gray-600">
                                {{ $plan->isYearly() ? __('spike::translations.per_year') : __('spike::translations.per_month') }}
                                @if($plan->hasPromotionCode() && $plan->discountRepeatsMonthly() && $plan->isMonthly())
                                    {{ trans_choice('spike::translations.discount_for_x_months', $plan->discount_repeats_months, ['months' => $plan->discount_repeats_months]) }}
                                    ,
                                    {{ __('spike::translations.after_discount') }}
                                    {{ $plan->priceFormatted() }}
                                    {{ $plan->isYearly() ? __('spike::translations.per_year') : __('spike::translations.per_month') }}
                                @elseif($plan->hasPromotionCode() && $plan->discountRepeatsOnce())
                                    {{ __('spike::translations.discount_once') }}
                                    , {{ __('spike::translations.after_discount') }}
                                    {{ $plan->priceFormatted() }}
                                    {{ $plan->isYearly() ? __('spike::translations.per_year') : __('spike::translations.per_month') }}
                                @endif
                            </p>
                        </dd>
                    </div>
                </dl>
            </div>
        </div>
    </section>
    @endif

    @if($plan->isPaid() && $allowDiscountCodes && !$promotionCode)
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
                <div>
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

    @if($cancellationOfferAccepted)
        <div class="px-4 sm:px-6 lg:px-8 mt-4 text-center">
            <div class="flex flex-col gap-3">
                <div class="text-green-800 text-lg font-semibold">
                    {{ __('spike::translations.offer_thank_you_title') }}
                </div>
                <div class="text-gray-700">
                    {{ __('spike::translations.offer_thank_you_subtitle') }}
                </div>
            </div>
        </div>
    @elseif(! empty($currentCancellationOffer))
        <div class="px-4 sm:px-6 lg:px-8 mt-4 text-center">
            @include($currentCancellationOffer['view'] ?? 'spike::components.offers.default', ['offer' => $currentCancellationOffer])
        </div>
    @endif

    <div class="mt-8 flex flex-col sm:flex-row sm:justify-end space-y-3 sm:space-y-0 sm:space-x-3 px-4 sm:px-6 lg:px-8">
        @if($plan->isPaid() && $isStripe)
            <div class="flex items-center bg-gray-50 border border-transparent rounded-md py-2 px-4 text-sm font-medium text-gray-600">
                @if(isset($paymentMethod))
                    @if($paymentMethod->type === 'card')
                    {!! __('spike::translations.using_payment_card', ['last_four' => '&bull;&bull;&bull;&bull; '.$paymentMethod->card->last4]) !!}
                    @else
                    {!! __('spike::translations.using_payment_method', ['name' => \Opcodes\Spike\Utils::paymentMethodName($paymentMethod)]) !!}
                    @endif
                @elseif(!$shouldLoadPaymentMethod)
                    {{ __('spike::translations.loading') }}
                @else
                    <a href="#" wire:click="$dispatch('openModal', { component: 'spike::add-payment-method' })"
                       class="text-brand font-semibold hover:opacity-80"
                    >{{ __('spike::translations.setup_payment_card') }}</a>
                @endif
            </div>
        @endif

        @if($cardDeclined)
            <div class="flex items-center text-sm font-medium gap-3">
                <a href="{{ route('spike.invoices') }}" class="whitespace-nowrap hover:opacity-80 bg-brand border border-transparent rounded-md shadow-sm py-3 sm:py-2 px-4 text-sm font-medium text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-gray-50 focus:ring-brand">
                    {{ __('spike::translations.update_payment_method') }}
                </a>
            </div>
        @endif

        @if($cancellationOfferAccepted)
        <button type="submit"
                class="hover:opacity-80 flex items-center justify-center bg-brand border border-transparent rounded-md shadow-sm py-3 sm:py-2 px-4 text-sm font-medium text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-gray-50 focus:ring-brand"
                wire:click.prevent="$dispatch('closeModal')"
                wire:loading.attr="disabled"
                wire:loading.class="opacity-50"
        >
            {{ __('spike::translations.offer_thank_you_close') }}
        </button>
        @elseif(! empty($currentCancellationOffer))
        <div class="flex justify-between gap-3">
            <button type="submit"
                    class="hover:opacity-80 flex items-center justify-center bg-transparent border border-gray-300 rounded-md shadow-sm py-3 sm:py-2 px-4 text-sm font-medium text-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-gray-50 focus:ring-gray-300"
                    wire:click.prevent="declineCancellationOffer"
                    wire:loading.attr="disabled"
                    wire:loading.class="opacity-50"
                    wire:target="declineCancellationOffer"
            >
                <x-spike::shared.spinner class="size-4 mr-2" wire:loading wire:target="declineCancellationOffer"/>
                {{ __('spike::translations.cancellation_offer_decline') }}
            </button>
            <button type="submit"
                    class="hover:opacity-80 flex items-center justify-center bg-brand border border-transparent rounded-md shadow-sm py-3 sm:py-2 px-4 text-sm font-medium text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-gray-50 focus:ring-brand"
                    wire:click.prevent="acceptCancellationOffer"
                    wire:loading.attr="disabled"
                    wire:loading.class="opacity-50"
                    wire:target="acceptCancellationOffer"
            >
                <x-spike::shared.spinner class="size-4 mr-2" wire:loading wire:target="acceptCancellationOffer"/>
                {{ __('spike::translations.cancellation_offer_accept') }}
            </button>
        </div>
        @elseif(! $cardDeclined)
        <button type="submit"
                class="@if(!$canSubscribe) opacity-50 @else hover:opacity-80 @endif flex items-center justify-center bg-brand border border-transparent rounded-md shadow-sm py-3 sm:py-2 px-4 text-sm font-medium text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-gray-50 focus:ring-brand"
                @if(!$canSubscribe) disabled @endif
                wire:click.prevent="subscribe"
                wire:loading.attr="disabled"
                wire:loading.class="opacity-50"
        >
            @if($plan->isFree())
                <x-spike::icons.dismiss-circle class="size-4 mr-2" wire:loading.remove wire:target="subscribe"/>
                <x-spike::shared.spinner class="size-4 mr-2" wire:loading wire:target="subscribe"/>
                {{ __('spike::translations.unsubscribe') }}
            @elseif($hasSubscription)
                <x-spike::icons.arrow-swap class="size-4 mr-2" wire:loading.remove wire:target="subscribe"/>
                <x-spike::shared.spinner class="size-4 mr-2" wire:loading wire:target="subscribe"/>
                {{ __('spike::translations.switch_subscription') }}
            @else
                <x-spike::icons.checkmark-lock class="size-4 mr-2" wire:loading.remove wire:target="subscribe"/>
                <x-spike::shared.spinner class="size-4 mr-2" wire:loading wire:target="subscribe"/>
                {{ __('spike::translations.subscribe') }}
            @endif
        </button>
        @endif
    </div>

    @if($cardDeclined)
        <div class="mt-3 text-sm font-semibold flex flex-col sm:flex-row sm:justify-end space-y-3 sm:space-y-0 sm:space-x-3 px-4 sm:px-6 lg:px-8">
            <div class="text-red-500">
                {{ __('spike::translations.card_declined_please_add') }}
            </div>
        </div>
    @endif
</form>
