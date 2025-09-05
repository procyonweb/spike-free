<div class="mb-6">
    @if(request()->query('state') === 'payment-method-updated')
        <div class="px-4 py-5 sm:px-6 text-sm bg-blue-100 text-gray-900 rounded-md shadow">
            <p class="mb-2 font-semibold">{{ __('spike::translations.payment_method_updated') }}</p>
            <p>{{ __('spike::translations.payment_method_updated_description') }}</p>
        </div>
    @else
    <div class="px-4 py-5 sm:px-6 text-sm bg-orange-100 text-orange-900 rounded-md shadow">
        <p class="mb-2 font-semibold">{{ __('spike::translations.subscription_past_due') }}</p>
        <p class="mb-3">{{ __('spike::translations.subscription_past_due_description') }}</p>

        <button
            data-allow-logout="false"
            data-transaction-id="{{ $transactionId }}"
            data-success-url="{{ route('spike.subscribe', ['state' => 'payment-method-updated']) }}"
            class="paddle_button text-sm font-medium flex items-center justify-center rounded-md px-4 py-2 text-white bg-brand hover:opacity-80"
        >
            <x-spike::icons.payment class="size-4 mr-2" />
            {{ __('spike::translations.update_payment_method') }}
        </button>
    </div>
    @endif
</div>
