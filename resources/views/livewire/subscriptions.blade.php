<div class="bg-white shadow overflow-hidden sm:rounded-md"
     @if($planToTrigger && \Opcodes\Spike\Facades\Spike::paymentProvider()->isStripe())
     x-init="$wire.subscribeTo('{{ $planToTrigger->payment_provider_price_id }}')"
     @endif
>
    <div class="bg-white px-4 py-5 border-b border-gray-200 sm:px-6 flex">
        <div class="flex-1">
            <h3 class="text-lg leading-6 font-medium text-gray-900">{{ __('spike::translations.subscriptions') }}</h3>
            <p class="mt-1 text-sm text-gray-600">{{ __('spike::translations.subscriptions_description') }}</p>
        </div>
        @if($hasAlternativePeriodPlans)
        <div class="flex-shrink-0 self-center text-sm whitespace-nowrap ml-3">
            <div class="flex items-center">
                <button wire:click="togglePeriod" type="button"
                        class="@if($yearly) bg-brand @else bg-gray-200 @endif relative inline-flex flex-shrink-0 h-6 w-11 border-2 border-transparent rounded-full cursor-pointer transition-colors ease-in-out duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand"
                        role="switch" aria-checked="false" aria-labelledby="annual-billing-label">
                    <span aria-hidden="true"
                          class="@if($yearly) translate-x-5 @else translate-x-0 @endif pointer-events-none inline-block size-5 rounded-full bg-white shadow transform ring-0 transition ease-in-out duration-200"></span>
                </button>
                <span class="ml-3" id="annual-billing-label">
                    <span class="text-sm font-medium text-gray-900">{{ __('spike::translations.annual_billing') }}</span>
                </span>
            </div>
        </div>
        @endif
    </div>

    <ul role="list" class="divide-y divide-gray-200">
        @php /** @var \Opcodes\Spike\SubscriptionPlan $subscription */ @endphp
        @foreach($subscriptions as $plan)
            <li>
                <div class="w-full">
                    <div class="px-4 py-4 flex items-center sm:px-6">
                        <div class="min-w-0 flex-1 sm:flex sm:items-center sm:justify-between">
                            <div class="truncate space-y-1">
                                <x-spike::shop.subscription-plan-heading :plan="$plan" class="mb-2"/>

                                @foreach($plan->provides_monthly as $providable)
                                    <x-spike::shop.providable-item
                                            :providable="$providable"
                                            :monthly="$providable instanceof \Opcodes\Spike\CreditAmount"
                                    />
                                @endforeach

                                @foreach($plan->features as $feature)
                                    <x-spike::shop.feature-item :feature="$feature"/>
                                @endforeach
                            </div>

                            <x-spike::shop.subscription-plan-pricing
                                    class="flex-shrink-0 mt-4 sm:mt-0 sm:ml-5"
                                    :plan="$plan"
                                    :cashier-subscription="$cashierSubscription"
                            />
                        </div>

                        @if(\Opcodes\Spike\Facades\Spike::paymentProvider()->isStripe())
                        <x-spike::shop.subscription-plan-buttons-stripe
                            class="ml-5"
                            :plan="$plan"
                            :cashier-subscription="$cashierSubscription"
                            :has-subscription="$hasSubscription"
                            :has-incomplete-payment="$hasIncompletePayment"
                            :free-plan-exists="$freePlanExists"
                        />
                        @elseif(\Opcodes\Spike\Facades\Spike::paymentProvider()->isPaddle())
                        <x-spike::shop.subscription-plan-buttons-paddle
                            class="ml-5"
                            :plan="$plan"
                            :cashier-subscription="$cashierSubscription"
                            :has-subscription="$hasSubscription"
                            :has-incomplete-payment="$hasIncompletePayment"
                            :free-plan-exists="$freePlanExists"
                        />
                        @endif
                    </div>
                </div>
            </li>
        @endforeach
    </ul>

    @if($planToTrigger && \Opcodes\Spike\Facades\Spike::paymentProvider()->isPaddle())
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                setTimeout(function () {
                    var button = document.getElementById('paddle-button-{{ $planToTrigger->payment_provider_price_id }}');
                    if (button) {
                        button.click();
                    }
                }, 500);
            });
        </script>
    @endif
</div>
