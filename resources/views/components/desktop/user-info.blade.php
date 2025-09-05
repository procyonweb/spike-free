<div class="relative inline-block text-left">
    <div class="w-full rounded-md px-3.5 text-sm text-left font-medium">
        <span class="flex w-full justify-between items-center">
            <span class="flex min-w-0 items-start justify-between space-x-3">
                @if($displayAvatar && !empty($avatarUrl))
                <img class="size-12 rounded-full flex-shrink-0 border border-white"
                     src="{{ $avatarUrl }}"
                     alt="{{ $billable->name }}" />
                @elseif($displayAvatar)
                <div class="flex-shrink-0 size-12 rounded-full border border-white bg-gray-900 bg-opacity-50 text-white p-2">
                    <x-spike::icons.person />
                </div>
                @endif
                <span class="flex-1 flex flex-col min-w-0">
                    <span class="mb-2 text-white text-sm font-medium truncate">{{ $billable->name }}</span>
                    <x-spike::desktop.credit-balances
                        :billable="$billable"
                        :display-empty-balances="config('spike.theme.display_empty_credit_balances', true)"
                    />
                </span>
            </span>
        </span>
    </div>
</div>
