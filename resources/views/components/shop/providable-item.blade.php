@props(['providable', 'monthly' => false])
<div {{ $attributes }}>
    <div class="flex items-center text-sm text-gray-600">
        <x-spike::shared.providable-icon
            :providable="$providable"
            class="flex-shrink-0 mr-1.5 h-5 w-auto opacity-75"
        />
        <p>
            {{ $providable->toString() }}
            @if($monthly){{ trans('spike::translations.per_month') }}@endif
        </p>
    </div>

    @if($providable instanceof \Opcodes\Spike\Contracts\Expirable && $providable->getExpiresAfter())
        <div class="mb-2 flex items-center text-xs text-gray-500">
            <span class="ml-1.5 -mt-1">&#8735;</span>
            <x-spike::icons.calendar-clock
                class="flex-shrink-0 mr-1.5 size-4 text-gray-400"/>
            <p>{{ __('spike::translations.credits_expire_after_purchase', ['duration' => $providable->getExpiresAfter()->forHumans()]) }}</p>
        </div>
    @endif
</div>
