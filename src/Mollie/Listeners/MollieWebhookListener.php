<?php

namespace Opcodes\Spike\Mollie\Listeners;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Events\OrderPaymentPaid;
use Opcodes\Spike\Actions\Products\ProvideCartProvidables;
use Opcodes\Spike\Actions\Subscriptions\ProvideSubscriptionPlanMonthlyProvides;
use Opcodes\Spike\Contracts\SpikeBillable;
use Opcodes\Spike\Contracts\SpikeSubscription;
use Opcodes\Spike\CreditTransaction;
use Opcodes\Spike\Events\SubscriptionActivated;
use Opcodes\Spike\Events\SubscriptionCancelled;
use Opcodes\Spike\Facades\Credits;
use Opcodes\Spike\Facades\PaymentGateway;
use Opcodes\Spike\Facades\Spike;

class MollieWebhookListener
{
    /**
     * Handle order payment paid event from Mollie.
     */
    public function handle(OrderPaymentPaid $event): void
    {
        $order = $event->order;
        $billable = $order->owner;

        // Check if this is a cart payment
        if ($metadata = $order->asMollieOrder()->metadata) {
            if (isset($metadata->spike_cart_id)) {
                $this->handleCartPayment($billable, $metadata->spike_cart_id);
            }
        }

        // Handle subscription renewal if this order is for a subscription
        if ($order->isForSubscription()) {
            $this->handleSubscriptionPayment($billable, $order);
        }
    }

    protected function handleCartPayment(SpikeBillable $billable, int $cartId): void
    {
        $query = config('spike.process_soft_deleted_carts')
            ? \Opcodes\Spike\Cart::withTrashed()->whereBillable($billable)
            : \Opcodes\Spike\Cart::whereBillable($billable);

        $cart = $query->find($cartId);

        if ($cart && !$cart->paid()) {
            $cart->markAsSuccessfullyPaid();

            // Provide cart providables (credits, etc.)
            app(ProvideCartProvidables::class)->handle($cart);
        }
    }

    protected function handleSubscriptionPayment(SpikeBillable $billable, $order): void
    {
        /** @var SpikeSubscription $subscription */
        $subscription = $billable->subscription();

        if (!$subscription || !$subscription->valid()) {
            return;
        }

        // Get the plan from the subscription
        $planId = $subscription->items->first()?->plan;

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

        // Update renewal date
        $subscription->cycle_started_at = now();
        $subscription->save();

        // Provide monthly subscription providables
        foreach ($subscription->items as $subscriptionItem) {
            app(ProvideSubscriptionPlanMonthlyProvides::class)
                ->handle($plan, $billable, $subscriptionItem);
        }

        event(new SubscriptionActivated($billable, $subscription, $plan));
    }
}
