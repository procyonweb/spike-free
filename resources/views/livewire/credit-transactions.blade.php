<div>
    <div class="bg-white shadow overflow-hidden lg:rounded-md">
        <div class="bg-white px-4 py-5 border-b border-gray-200 sm:px-6">
            <div class="-ml-4 -mt-4 flex justify-between items-center flex-wrap sm:flex-nowrap">
                <div class="ml-4 mt-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">{{ __('spike::translations.credit_transactions') }}</h3>
                    <p class="mt-1 text-sm text-gray-600">{{ __('spike::translations.credit_transactions_description') }}</p>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="inline-block min-w-full table-auto divide-y divide-gray-300">
                <tbody class="overflow-hidden divide-y divide-gray-200 bg-white">
                @php /** @var \Opcodes\Spike\CreditTransaction $transaction */ @endphp
                @foreach($transactions as $transaction)
                    <tr id="credit-transaction-{{ $transaction->id }}">
                        <td class="whitespace-nowrap py-2 pl-4 pr-3 text-sm text-right font-medium sm:pl-6 @if($transaction->isUsage()) text-red-700 @elseif($transaction->expired()) text-gray-400 @else text-gray-800 @endif">
                            <div class="flex items-center justify-end">
                                @if($transaction->credits < 0)
                                    <span class="mr-1">-</span>
                                @endif
                                {{ number_format(abs($transaction->credits)) }}
                                <x-spike::shared.providable-icon :providable="$transaction->credit_type" class="size-4 ml-1 opacity-60" />
                            </div>
                        </td>
                        <td class="whitespace-nowrap py-2 px-2 text-sm text-gray-600">
                            @if($transaction->isProduct() || $transaction->isSubscription())
                                <span class="inline-block w-full text-center px-2 py-1 rounded-md bg-green-50 text-green-700 text-xs font-medium">{{ $transaction->type_translated }}</span>
                            @elseif($transaction->isAdjustment())
                                <span class="inline-block w-full text-center px-2 py-1 rounded-md bg-blue-50 text-blue-700 text-xs font-medium">{{ $transaction->type_translated }}</span>
                            @elseif($transaction->isUsage())
                                <span class="inline-block w-full text-center px-2 py-1 rounded-md bg-red-50 text-red-700 text-xs font-medium">{{ $transaction->type_translated }}</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap py-2 px-2 text-sm text-gray-600 w-full">
                            @if($transaction->expired() && !$transaction->isUsage())
                                <span class="px-2 py-1 rounded-md bg-gray-100 text-gray-700 text-xs font-medium mr-1">{{ __('spike::translations.expired_on', ['date' => $transaction->expiryDateFormatted()]) }}</span>
                            @endif
                            {{ $transaction->fullNotes() }}
                        </td>
                        <td class="relative whitespace-nowrap py-2 pl-3 pr-4 text-right text-sm text-gray-500 sm:pr-6">
                            {{ $transaction->createdAtFormatted() }}
                        </td>
                    </tr>
                @endforeach

                @if(!$loadTransactions)
                    <tr>
                        <td colspan="4"
                            class="flex items-center justify-center px-4 py-4 sm:px-6 text-sm text-gray-600">
                            <x-spike::shared.spinner class="size-4 mr-2" />
                            {{ __('spike::translations.loading') }}
                        </td>
                    </tr>
                @elseif($transactions->isEmpty())
                    <tr>
                        <td colspan="4"
                            class="flex items-center justify-center px-4 py-4 sm:px-6 text-sm text-gray-600">
                            {{ __('spike::translations.credit_transactions_empty') }}
                        </td>
                    </tr>
                @endempty
                </tbody>
            </table>
        </div>

        @if($transactions->isNotEmpty() && $transactions->total() > 10)
            <div class="bg-white px-4 py-5 border-t border-gray-200 sm:px-6">
                {{ $transactions->links('spike::components.shared.pagination') }}
            </div>
        @endif
    </div>
</div>
