@props(['plan', 'cashierSubscription' => null])
<div {{ $attributes->merge(['class' => 'flex flex-col sm:items-end justify-center']) }}>
    <p class="text-xl font-bold text-gray-800">
        @if($cashierSubscription && $plan->payment_provider_price_id === $cashierSubscription->stripe_price && ! $cashierSubscription->hasPaymentCard())
        <span class="line-through opacity-50">{{ $plan->monthlyPriceFormatted() }}</span>
        <span>{{ \Opcodes\Spike\Utils::formatAmount(0) }}</span>
        @else
            @if($plan->hasPromotionCode())
                <span class="text-gray-400 line-through">{{ $plan->monthlyPriceFormatted() }}</span>
            @endif
        {{ $plan->monthlyPriceAfterDiscountFormatted() }}
        @endif
        <span class="text-sm text-gray-600">{{ __('spike::translations.per_month') }}</span>
    </p>
    @if($plan->isYearly())
    <p class="text-sm font-semibold text-gray-600 mt-0">
        <span class="text-xs text-gray-400">{{ __('spike::translations.charged') }}</span>
        {{ $plan->priceAfterDiscountFormatted() }}
        <span class="text-xs text-gray-400">{{ __('spike::translations.per_year') }}</span>
    </p>
    @endif
    @if($plan->isCancelled())
    <div class="flex text-sm text-red-600">
        {{ __('spike::translations.ends_on_date', ['date' => $plan->ends_at?->translatedFormat(config('spike.date_formats.subscription_end_date', 'F j, Y'))]) }}
    </div>
    @endif
</div>
