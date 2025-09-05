<div class="w-full relative flex flex-col bg-white pt-6 pb-8 overflow-hidden sm:pb-6 sm:rounded-lg lg:py-8"
    x-data="{
        async onPaymentFormSubmit() {
            const errorMessageContainer = document.getElementById('error-message');
            const submitButton = document.getElementById('submit-button');
            errorMessageContainer.textContent = '';
            errorMessageContainer.style.display = 'none';
            const cardHolderName = document.getElementById('cardholder-name');
            const clientSecret = document.getElementById('stripe-intent-client-secret').value;
            submitButton.setAttribute('disabled', 'disabled');
            submitButton.classList.add('opacity-50');

            const { setupIntent, error } = await window.stripe.confirmCardSetup(
                clientSecret, {
                    payment_method: {
                        card: cardElement,
                        billing_details: { name: cardHolderName.value }
                    }
                },
            );

            if (error) {
                submitButton.removeAttribute('disabled');
                submitButton.classList.remove('opacity-50');
                console.error(error);
                errorMessageContainer.textContent = error.message;
                errorMessageContainer.style.display = 'block';
            } else {
                $wire.call('addPaymentMethod', setupIntent.payment_method);
            }
        },

        initStripeElement() {
            const apiKey = '{{ config('cashier.key') }}';
            const returnUrl = '{{ $returnUrl ?? request()->fullUrl() }}';
            const stripeScriptTagId = 'stripe-script-tag';
            window.stripe = null;
            window.elements = null;
            window.cardElement = null;

            function setupStripe() {
                window.stripe = Stripe(apiKey);
                window.elements = window.stripe.elements();
                window.cardElement = window.elements.create('card');
                window.cardElement.mount('#card-element');
            }
            // Set up the Stripe element
            if (!document.getElementById(stripeScriptTagId)) {
                const stripeScriptTag = document.createElement('script');
                stripeScriptTag.setAttribute('src', 'https://js.stripe.com/v3/');
                stripeScriptTag.setAttribute('id', stripeScriptTagId);
                document.body.appendChild(stripeScriptTag);
                stripeScriptTag.addEventListener('load', () => {
                    setupStripe();
                })
            } else {
                setupStripe();
            }
        },
    }"
    x-init="$wire.call('loadStripe'); $nextTick(function () { document.getElementById('cardholder-name').focus(); }); $wire.on('initStripeElement', () => initStripeElement());"
>
    <div class="flex items-center justify-between px-4 sm:px-6 lg:px-8">
        <h2 class="text-lg font-medium text-gray-900">{{ __('spike::translations.add_payment_card') }}</h2>
        <button type="button" class="text-gray-400 hover:text-gray-500" tabindex="4"
                wire:click="$dispatch('closeModal')"
                wire:loading.attr="disabled"
                wire:loading.class="opacity-50"
                wire:target="addPaymentMethod"
        >
            <span class="sr-only">{{ __('spike::translations.close_modal') }}</span>
            <x-spike::icons.dismiss class="size-6" />
        </button>
    </div>

    <section aria-labelledby="cart-heading" class="mt-6 sm:mt-8 px-4 sm:px-6 lg:px-8">
        <h2 id="cart-heading" class="sr-only">
            {{ __('spike::translations.add_payment_card_subtitle') }}
        </h2>

        <div>
            <form id="add-payment-method-form" x-on:submit.prevent="onPaymentFormSubmit">
                <div class="md:flex">
                    <div class="flex-1 md:mr-3">
                        <label for="cardholder-name" class="block sr-only sm:hidden text-gray-700 text-sm">
                            {{ __('spike::translations.cardholder_name') }}
                        </label>
                        <div class="relative">
                            <input type="text" name="cardholder-name" id="cardholder-name" required autofocus
                                   class="pl-10 shadow-sm block w-full border-gray-200 rounded-md text-sm"
                                   placeholder="{{ __('spike::translations.cardholder_name_placeholder') }}"
                                   x-on:keydown.tab="window.cardElement.focus();"
                            >
                            <x-spike::icons.person class="absolute left-[0.80rem] top-[0.5rem] size-5 text-gray-300" />
                        </div>
                    </div>

                    <input type="hidden" id="stripe-intent-client-secret" value="{{ $stripeIntentClientSecret ?? '' }}" />

                    <div class="h-10 md:w-3/5 mt-4 md:mt-0 pl-3 pr-3 md:pr-6 py-2.5 bg-white rounded-md border border-gray-200 inline-block w-full shadow-sm">
                        <div id="card-element">
                            <!-- Elements will create form elements here -->
                        </div>
                    </div>
                </div>

                <p id="error-message" style="display: none;" class="mt-2 text-sm text-red-600"></p>

                <div class="mt-4 md:flex justify-end">
                    <button tabindex="3" id="submit-button" type="submit"
                            class="flex-shrink-0 inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-brand hover:opacity-80 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand @if(!$stripeIntentClientSecret) opacity-50 @endif"
                            @if(!$stripeIntentClientSecret) disabled="disabled" @endif
                    >
                        <x-spike::shared.spinner class="size-4 mr-2" wire:loading wire:target="addPaymentMethod" />
                        <x-spike::icons.checkmark-lock class="size-4 mr-2" wire:loading.remove wire:target="addPaymentMethod" />
                        {{ __('spike::translations.add_card') }}
                    </button>
                </div>
            </form>
        </div>
    </section>

</div>
