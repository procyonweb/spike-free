@php
$displayInfo = \Opcodes\Spike\Utils::paymentMethodName($paymentMethod);
$expiresAt = match($paymentMethod['type']) {
    'card' => \Illuminate\Support\Carbon::create($paymentMethod['card']['exp_year'], $paymentMethod['card']['exp_month']),
    default => null
};
$isCardPayment = in_array($paymentMethod['type'], ['card', 'card_present', 'link', 'us_bank_account']);
@endphp
<div class="min-w-0 flex items-center">
    <div class="flex-shrink-0">
        @if($isCardPayment)
            <x-spike::icons.payment class="size-12 text-gray-400" />
        @else
            <x-spike::icons.building-bank class="size-12 text-gray-400" />
        @endif
    </div>
    <div class="min-w-0 flex-1 px-4">
        <div>
            <p class="text-sm font-medium text-gray-800 truncate">
                {{ $displayInfo }}
            </p>
            @if($expiresAt)
            <p class="mt-0.5 flex items-center text-sm text-gray-600">
                <span class="truncate">{{ __('spike::translations.expires_on_date', ['date' => $expiresAt->translatedFormat(config('spike.date_formats.payment_method_expiry', 'F, Y'))]) }}</span>
            </p>
            @endif
        </div>
    </div>
</div>
