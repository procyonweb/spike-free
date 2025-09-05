<div class="flex-1 flex justify-end px-4 sm:px-6 lg:px-8">
    <div class="flex items-center">
        <span class="flex min-w-0 items-center justify-between">
            <span class="flex-1 flex flex-col items-end min-w-0">
                <span class="text-white text-sm font-medium truncate">{{ $billable->name }}</span>
                <x-spike::mobile.credit-balances
                    :billable="$billable"
                    :display-empty-balances="config('spike.theme.display_empty_credit_balances', true)"
                />
            </span>
            @if($displayAvatar && !empty($avatarUrl))
            <img class="size-10 border border-white rounded-full flex-shrink-0 ml-3"
                 src="{{ $avatarUrl }}"
                 alt="{{ $billable->name }}">
            @elseif($displayAvatar)
            <div class="relative flex-shrink-0 ml-3 size-10 rounded-full border border-white bg-gray-900 bg-opacity-50 text-white p-2">
                <x-spike::icons.person />
            </div>
            @endif
        </span>
    </div>
</div>
