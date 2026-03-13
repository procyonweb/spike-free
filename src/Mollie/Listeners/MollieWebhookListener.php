<?php

namespace Opcodes\Spike\Mollie\Listeners;

use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Events\OrderPaymentPaid;
use Opcodes\Spike\Actions\Subscriptions\ProvideSubscriptionPlanMonthlyProvides;
use Opcodes\Spike\Events\SubscriptionActivated;
use Opcodes\Spike\Facades\Spike;

class MollieWebhookListener
{
    public function handle(OrderPaymentPaid $event): void
    {
        $order = $event->order;
        $billable = $order->owner;

        if (!$billable) {
            return;
        }

        $hasSubscriptionItem = $order->items()
            ->whereNotNull('orderable_type')
            ->whereNotNull('orderable_id')
            ->get()
            ->contains(fn ($item) => $item->orderable instanceof \Laravel\Cashier\Subscription);

        if ($hasSubscriptionItem) {
            $this->handleSubscriptionRenewal($billable);
        }
    }

    protected function handleSubscriptionRenewal($billable): void
    {
        $subscription = $billable->subscription();

        if (!$subscription || !$subscription->valid()) {
            return;
        }

        $planId = $subscription->plan;

        if (!$planId) {
            return;
        }

        $plan = Spike::findSubscriptionPlan($planId, $billable);

        if (!$plan) {
            Log::warning('[Spike\MollieWebhookListener] Could not find subscription plan for order.', [
                'billable_id' => $billable->getKey(),
                'plan_id' => $planId,
            ]);
            return;
        }

        foreach ($subscription->items() as $subscriptionItem) {
            app(ProvideSubscriptionPlanMonthlyProvides::class)
                ->handle($plan, $billable, $subscriptionItem);
        }

        event(new SubscriptionActivated($billable, $plan));
    }
}
