@props(['plan', 'cashierSubscription' => null, 'hasSubscription' => false])
@php
$paddleCheckout = \Opcodes\Spike\Facades\Spike::resolve()
    ->subscribe($plan->payment_provider_price_id)
    ->returnTo(route('spike.subscribe', ['success' => true]));
@endphp
<div {{ $attributes->merge(['class' => 'min-w-[130px] flex justify-end']) }}>
    @if($plan->isCurrent() && !$plan->isCancelled() && $freePlanExists)
    <div class="text-sm font-medium w-full flex items-center justify-center rounded-md px-4 py-2 bg-transparent text-brand">
        <x-spike::icons.checkmark-circle class="size-4 mr-2" />
        {{ __('spike::translations.current') }}
    </div>
    @elseif($plan->isCurrent() && !$plan->isCancelled() && !$freePlanExists)
    <button
        class="text-sm font-medium w-full flex items-center justify-center rounded-md px-4 py-2 bg-transparent text-red-700 hover:opacity-80 disabled:opacity-50 disabled:hover:opacity-50"
        wire:click="unsubscribe" wire:loading.attr="disabled" wire:loading.class="opacity-50" wire:target="unsubscribe"
        @if($hasIncompletePayment) disabled="disabled" @endif
    >
        <x-spike::icons.dismiss-circle class="size-4 mr-2" />
        {{ __('spike::translations.unsubscribe') }}
    </button>
    @elseif($plan->isCancelled())
    <button
        class="text-sm font-medium w-full flex items-center justify-center rounded-md px-4 py-2 text-white bg-brand hover:opacity-80 disabled:opacity-50 disabled:hover:opacity-50"
        wire:click="resumePlan('{{ $plan->payment_provider_price_id }}')"
        wire:loading.attr="disabled"
        wire:loading.class="opacity-50"
        @if($hasIncompletePayment) disabled="disabled" @endif
    >
        <x-spike::icons.play-circle class="size-4 mr-2" wire:loading.remove wire:target="resumePlan" />
        <x-spike::shared.spinner class="size-4 mr-2" wire:loading wire:target="resumePlan" />
        {{ __('spike::translations.resume') }}
    </button>
    @elseif($hasSubscription)
    <button
        class="text-sm font-medium w-full flex items-center justify-center rounded-md px-4 py-2 text-white bg-brand hover:opacity-80 disabled:opacity-50 disabled:hover:opacity-50"
        wire:click="subscribeTo('{{ $plan->payment_provider_price_id }}')"
        wire:loading.attr="disabled"
        wire:loading.class="opacity-50"
        wire:target="subscribeTo"
        @if($hasIncompletePayment) disabled="disabled" @endif
    >
        <x-spike::icons.arrow-swap class="size-4 mr-2" />
        {{ __('spike::translations.switch') }}
    </button>
    @else
    <x-spike::shop.paddle-button
        id="paddle-button-{{ $plan->payment_provider_price_id }}"
        :checkout="$paddleCheckout"
        class="text-sm font-medium w-full flex items-center justify-center rounded-md px-4 py-2 text-white bg-brand hover:opacity-80 disabled:opacity-50 disabled:hover:opacity-50"
        :disabled="$hasIncompletePayment"
    >
        <x-spike::icons.payment class="size-4 mr-2" />
        {{ __('spike::translations.subscribe') }}
    </x-spike::shop.paddle-button>
    @endif
</div>
