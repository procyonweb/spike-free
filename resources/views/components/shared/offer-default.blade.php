<div class="flex flex-col gap-4">
    <div class="text-gray-800 text-lg font-semibold">
        {{ __('spike::translations.cancellation_offer_title') }}
    </div>
    <div class="text-gray-700">
        {{ __('spike::translations.cancellation_offer_subtitle') }}
    </div>
    <div class="bg-sky-100 border border-sky-200 rounded-md py-6 md:py-8 px-5 text-gray-900">
        <div class="text-sm font-medium text-gray-600 mb-3">{{ __('spike::translations.cancellation_offer_special') }}</div>
        <div class="text-lg font-bold">{{ $offer['name'] }}</div>
    </div>
    @if(!empty($offer['description']))
    <div class="relative">
        {{ $offer['description'] }}
    </div>
    @endif
</div>