<x-spike::layout>
    <x-slot:title>
        {{ __('spike::translations.billing') }}
    </x-slot:title>

    @if(\Opcodes\Spike\Facades\Spike::paymentProvider()->isStripe())
    <div class="mb-12">
        <livewire:spike::stripe-payment-methods />
    </div>
    @elseif(\Opcodes\Spike\Facades\Spike::paymentProvider()->isPaddle() && \Opcodes\Spike\Facades\Spike::resolve()->isSubscribed())
    <div class="mb-12">
        <livewire:spike::paddle-payment-method />
    </div>
    @endif

    <div>
        <livewire:spike::invoices />
    </div>
</x-spike::layout>
