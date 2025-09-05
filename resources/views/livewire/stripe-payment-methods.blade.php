<div wire:init="loadPaymentMethods">
    <div class="bg-white shadow overflow-hidden sm:rounded-md">
        <div class="bg-white px-4 py-5 border-b border-gray-200 sm:px-6">
            <div class="-ml-4 -mt-4 flex justify-between items-center flex-wrap sm:flex-nowrap">
                <div class="ml-4 mt-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">{{ __('spike::translations.payment_cards') }}</h3>
                    <p class="mt-1 text-sm text-gray-600">
                        {{ __('spike::translations.payment_cards_description') }}
                    </p>
                </div>
                <div class="ml-4 mt-4 flex-shrink-0">
                    <button type="button"
                            wire:click="$dispatch('openModal', { component: 'spike::add-payment-method' })"
                            class="relative inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-brand hover:opacity-80 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand">
                        {{ __('spike::translations.add_new_card') }}
                    </button>
                </div>
            </div>
        </div>

        <ul role="list" class="divide-y divide-gray-200">
            @php /** @var Laravel\Cashier\PaymentMethod $paymentMethod */ @endphp
            @foreach($paymentMethods as $paymentMethod)
                <li>
                    <div class="block">
                        <div class="flex items-center px-4 py-4 sm:px-6">
                            <x-spike::shop.stripe-payment-method-info :$paymentMethod />
                            <div>
                                @if($paymentMethod['id'] === $defaultPaymentMethodId)
                                    <span class="ml-4 bg-green-50 text-green-700 font-medium rounded-md px-2 py-1 text-xs">{{ __('spike::translations.default') }}</span>
                                @endif
                            </div>
                            <div class="flex-1 flex justify-end">
                                @if($paymentMethod['id'] !== $defaultPaymentMethodId)
                                <span
                                    class="text-blue-600 text-sm font-medium px-2 py-1 hover:text-blue-800 cursor-pointer mr-3 "
                                    wire:click="setDefaultMethod('{{ $paymentMethod['id'] }}')"
                                    wire:loading.attr="disabled"
                                    wire:loading.class="opacity-50"
                                >
                                    {{ __('spike::translations.make_default') }}
                                </span>
                                @endif

                                <span
                                    class="text-red-600 text-sm font-medium px-2 py-1 hover:text-red-800 cursor-pointer"
                                    wire:click="deletePaymentMethod('{{ $paymentMethod['id'] }}')"
                                    wire:loading.attr="disabled"
                                    wire:loading.class="opacity-50"
                                >
                                    {{ __('spike::translations.remove') }}
                                </span>
                            </div>
                        </div>
                    </div>
                </li>
            @endforeach

            @if(!$paymentMethodsLoaded)
                <li>
                    <div class="block">
                        <div class="flex items-center justify-center px-4 py-4 sm:px-6 text-sm text-gray-600">
                            <x-spike::shared.spinner class="size-4 mr-2" />
                            {{ __('spike::translations.loading') }}
                        </div>
                    </div>
                </li>
            @elseif(empty($paymentMethods))
                <li>
                    <div class="block">
                        <div class="flex items-center justify-center px-4 py-4 sm:px-6 text-sm text-gray-600">
                            {{ __('spike::translations.payment_cards_empty') }}
                        </div>
                    </div>
                </li>
            @endempty
        </ul>
    </div>
</div>
