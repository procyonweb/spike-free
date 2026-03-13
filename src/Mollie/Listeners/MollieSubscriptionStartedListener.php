<?php

namespace Opcodes\Spike\Mollie\Listeners;

use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Events\SubscriptionStarted;
use Opcodes\Spike\Actions\Subscriptions\ProvideSubscriptionPlanMonthlyProvides;
use Opcodes\Spike\Events\SubscriptionActivated;
use Opcodes\Spike\Facades\Spike;

class MollieSubscriptionStartedListener
{
    public function handle(SubscriptionStarted $event): void
    {
        $subscription = $event->subscription;
        $billable = $subscription->owner;

        if (!$billable) {
            return;
        }

        $plan = Spike::findSubscriptionPlan($subscription->plan, $billable);

        if (!$plan) {
            Log::warning('[Spike\MollieSubscriptionStartedListener] Could not find subscription plan.', [
                'billable_id' => $billable->getKey(),
                'plan_id' => $subscription->plan,
            ]);
            return;
        }

        foreach ($subscription->items() as $subscriptionItem) {
            app(ProvideSubscriptionPlanMonthlyProvides::class)
                ->handle($plan, $billable, $subscriptionItem);
        }

        event(new SubscriptionActivated($billable, $subscription, $plan));
    }
}
