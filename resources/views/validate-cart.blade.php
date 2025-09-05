<x-spike::layout>

    <x-slot:title>
        {{ __('spike::translations.validating_purchase') }}
    </x-slot:title>

    <h1 class="text-sm font-medium text-orange-600">{{ __('spike::translations.payment_processing') }}</h1>
    <p class="mt-2 text-3xl font-extrabold tracking-tight text-gray-900 sm:text-4xl">{{ __('spike::translations.validating_purchase_title') }}</p>
    <p class="mt-6 text-base text-gray-600">{{ __('spike::translations.validating_purchase_desc_1') }}</p>

    @livewire('spike::validate-cart', ['cart' => $cart])

</x-spike::layout>
