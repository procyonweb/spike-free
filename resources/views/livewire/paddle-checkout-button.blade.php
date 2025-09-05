<x-spike::shop.paddle-button
    id="paddle-button-checkout"
    :checkout="$paddleCheckout"
    class="flex items-center bg-brand hover:opacity-80 text-white rounded-md shadow px-3 py-2 text-sm"
>
    <x-spike::icons.cart class="size-5 mr-2" />
    {{ __('spike::translations.checkout') }}
</x-spike::shop.paddle-button>
