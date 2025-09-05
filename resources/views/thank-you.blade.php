<x-spike::layout>

    <x-slot:title>
        {{ __('spike::translations.thank_you') }}
    </x-slot:title>

    <div class="px-4 sm:px-6 lg:px-0">
        <h1 class="text-sm font-medium text-brand">{{ __('spike::translations.payment_successful') }}</h1>
        <p class="mt-2 text-3xl font-extrabold tracking-tight text-gray-900 sm:text-4xl">{{ __('spike::translations.thanks_for_ordering') }}</p>
        <p class="mt-6 text-base text-gray-600">
            {{ __('spike::translations.order_final_note_1') }}
            @foreach($cart->totalProvides() as $provide)@if($loop->last && $loop->count > 1) {{ __('spike::translations.and') }} @elseif(!$loop->first)<span>,</span>@endif
                <span class="inline-flex items-baseline font-semibold">
                    <x-spike::shared.providable-icon :providable="$provide" class="size-4 inline-block mr-1 relative -bottom-0.5 opacity-75" />
                    {{ $provide->toString() }}
                </span>@endforeach
            {{ __('spike::translations.order_final_note_2') }}
        </p>
        <p class="mt-3 text-gray-600">
            {!! __('spike::translations.order_final_note_3', [
                'billing_portal' => '<a href="'.route('spike.invoices').'" class="text-brand hover:opacity-80 font-semibold">'.__('spike::translations.billing_portal').'</a>'
            ]) !!}
        </p>

        <x-spike::shop.redirect :redirect-to="$redirect_to" :redirect-delay="$redirect_delay" />
    </div>

</x-spike::layout>
