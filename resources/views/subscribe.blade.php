<x-spike::layout>

    <x-slot:title>
        {{ __('spike::translations.subscribe') }}
    </x-slot:title>

    @if($success)
    <div class="px-4 py-5 sm:px-6 mb-6 text-sm bg-white text-gray-600 rounded-md shadow">
        <p class="font-semibold flex items-center text-brand">
            <x-spike::icons.checkmark-circle class="size-4 mr-2" />
            {{ __('spike::translations.subscription_success') }}
        </p>
        <p class="mt-2">{{ __('spike::translations.subscription_success_description') }}</p>

        <x-spike::shop.redirect :redirect-to="$redirect_to" :redirect-delay="$redirect_delay" />
    </div>
    @endif

    @if($subscription?->isPastDue() && $subscription->paddle_id)
        <livewire:spike::update-payment-method-paddle :subscription="$subscription" />
    @endif

    <div>
        <livewire:spike::subscriptions :preselected-plan="$preselectedPlan" />
    </div>

</x-spike::layout>
