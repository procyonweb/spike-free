@props(['billable', 'displayEmptyBalances' => true])
@foreach($billable->credits()->allBalances() as $creditBalance)
    @if(!$displayEmptyBalances && $creditBalance->balance() === 0) @continue @endif
    <span class="text-white text-sm truncate flex items-center">
        <x-spike::shared.providable-icon :providable="$creditBalance->type()" class="flex-shrink-0 mr-2 size-4" />
        <span class="font-bold mr-1.5">{{ number_format($creditBalance->balance()) }}</span>
        {{ $creditBalance->type()->name($creditBalance->balance()) }}
    </span>
@endforeach
