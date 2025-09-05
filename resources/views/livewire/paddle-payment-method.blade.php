<div wire:init="loadPaymentMethod">
    <div class="bg-white shadow overflow-hidden sm:rounded-md">
        <div class="bg-white px-4 py-5 border-b border-gray-200 sm:px-6">
            <div class="-ml-4 -mt-4 flex justify-between items-center flex-wrap sm:flex-nowrap">
                <div class="ml-4 mt-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">{{ __('spike::translations.payment_method') }}</h3>
                    <p class="mt-1 text-sm text-gray-600">
                        @if($shouldLoadPaymentMethod && $paymentMethod)
                        {{ __('spike::translations.payment_method_description', ['card_brand' => $paymentMethod->card_type, 'last_four' => $paymentMethod->card_last_four]) }}
                        @elseif($shouldLoadPaymentMethod && !$paymentMethod)
                        {{ __('spike::translations.no_payment_method') }}
                        @else
                        <div class="flex items-center mt-3 text-sm text-gray-600">
                            <x-spike::shared.spinner class="size-4 mr-2" />
                            {{ __('spike::translations.loading') }}
                        </div>
                       @endif
                    </p>
                </div>

                <div class="ml-4 mt-4 flex-shrink-0 @if(!isset($transactionId)) hidden @endif">
                    <button
                        data-allow-logout="false"
                        data-transaction-id="{{ $transactionId }}"
                        data-success-url="{{ route('spike.invoices') }}"
                        class="paddle_button text-sm font-medium flex items-center justify-center rounded-md px-4 py-2 text-white bg-brand hover:opacity-80"
                    >
                        <x-spike::icons.payment class="size-4 mr-2" />
                        {{ __('spike::translations.update_payment_method') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
