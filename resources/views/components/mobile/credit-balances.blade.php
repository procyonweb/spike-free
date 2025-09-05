@props(['billable', 'displayEmptyBalances' => true])
<span class="flex items-center space-x-3">
    @foreach($billable->credits()->allBalances() as $creditBalance)
        @if(!$displayEmptyBalances && $creditBalance->balance() === 0) @continue @endif
        <span class="text-white text-sm truncate flex items-center">
            <x-spike::shared.providable-icon :providable="$creditBalance->type()" class="flex-shrink-0 mr-1.5 size-4" />
            <span class="font-bold">{{ number_format($creditBalance->balance()) }}</span>
            @if($loop->count <= 2){{ $creditBalance->type()->name($creditBalance->balance()) }}@endif
        </span>
    @endforeach
</span>
