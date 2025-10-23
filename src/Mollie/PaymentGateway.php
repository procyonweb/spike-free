<?php

namespace Opcodes\Spike\Mollie;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Cashier;
use Opcodes\Spike\Cart;
use Opcodes\Spike\Contracts\PaymentGatewayContract;
use Opcodes\Spike\Contracts\SpikeSubscription;
use Opcodes\Spike\Facades\Spike;
use Opcodes\Spike\PaymentProvider;
use Opcodes\Spike\SubscriptionPlan;
use Opcodes\Spike\Traits\ScopedToBillable;

class PaymentGateway implements PaymentGatewayContract
{
    use ScopedToBillable;

    static string $subscriptionName = 'default';

    public function provider(): PaymentProvider
    {
        return PaymentProvider::Mollie;
    }

    public function findBillable($customer_id)
    {
        return Cashier::findBillable($customer_id);
    }

    public function payForCart(Cart $cart): bool
    {
        $billable = $this->getBillable();

        // Validate currency consistency and determine cart currency
        $cartCurrency = $cart->validateAndDetermineCurrency();

        $orderItems = [];
        foreach ($cart->items as $item) {
            $product = $item->product();
            $orderItems[] = [
                'description' => $product->name,
                'price' => $product->price_in_cents,
                'quantity' => $item->quantity,
                'currency' => $product->currency ?? config('cashier.currency', 'EUR'),
            ];
        }

        $order = $billable->newOrder();

        foreach ($orderItems as $orderItem) {
            $order->addItem($orderItem);
        }

        // Add metadata for cart tracking
        $order->metadata = [
            'spike_cart_id' => $cart->id,
        ];

        if ($cart->hasPromotionCode()) {
            // TODO: Implement Mollie coupon/discount if supported
            Log::warning('[Spike\MollieGateway] Promotion codes are not yet implemented for Mollie.');
        }

        $processedOrder = $order->processPayment();

        return !is_null($processedOrder);
    }

    public function invoiceAndPayItems(array $items, array $options = []): bool
    {
        $billable = $this->getBillable();

        $order = $billable->newOrder();

        foreach ($items as $priceId => $quantity) {
            // TODO: Fetch price details from Mollie API
            $order->addItem([
                'description' => 'Item ' . $priceId,
                'price' => 0, // This needs to be fetched from Mollie
                'quantity' => $quantity,
            ]);
        }

        $processedOrder = $order->processPayment();

        return !is_null($processedOrder);
    }

    /**
     * @return SpikeSubscription|Subscription|null
     * @throws \Exception
     */
    public function getSubscription(): ?SpikeSubscription
    {
        return $this->getBillable()->subscription(self::$subscriptionName);
    }

    public function getRenewalDate(): ?CarbonInterface
    {
        $subscription = $this->getSubscription();

        if (!$subscription || !$subscription->valid()) {
            return null;
        }

        return $subscription->renewalDate();
    }

    public function subscribed(?SubscriptionPlan $plan = null): bool
    {
        return $this->getBillable()->subscribed(
            self::$subscriptionName,
            optional($plan)->payment_provider_price_id
        );
    }

    public function createSubscription(SubscriptionPlan $plan, bool $requirePaymentCard = true): SpikeSubscription
    {
        if ($requirePaymentCard) {
            $billable = $this->getBillable();
            $promotionCode = $billable->stripePromotionCode(); // TODO: Adapt for Mollie

            $subscription = $billable
                ->newSubscription(self::$subscriptionName, $plan->payment_provider_price_id)
                ->create();

            if ($promotionCode) {
                $subscription->forceFill([
                    'promotion_code_id' => $promotionCode->id ?? null
                ])->save();
            }

            /** @noinspection PhpIncompatibleReturnTypeInspection */
            return $subscription;
        }

        $subscription = $this->getBillable()->subscriptions()->create([
            'type' => self::$subscriptionName,
            'mollie_subscription_id' => '',
            'mollie_plan' => $plan->payment_provider_price_id,
            'plan' => $plan->payment_provider_price_id,
            'ends_at' => null,
            'renews_at' => $plan->isYearly() ? now()->addYear() : now()->addMonthNoOverflow(),
        ]);

        $subscription->items()->create([
            'mollie_subscription_id' => '',
            'plan' => $plan->payment_provider_price_id,
            'quantity' => 1,
        ]);

        return $subscription;
    }

    public function switchSubscription(SubscriptionPlan $plan, bool $requirePaymentCard = true): SpikeSubscription
    {
        $existingSubscription = $this->getSubscription();

        if ($requirePaymentCard && $existingSubscription->hasPaymentCard()) {
            return $this->getSubscription()->swap($plan->payment_provider_price_id);
        }

        if ($requirePaymentCard && ! $existingSubscription->hasPaymentCard()) {
            $subscription = $this->createSubscription($plan, $requirePaymentCard);
            $existingSubscription->delete();

            return $subscription;
        }

        // without payment card, we need to manually update the subscription
        $subscription = $this->getSubscription();
        $subscription->plan = $plan->payment_provider_price_id;
        $subscription->mollie_plan = $plan->payment_provider_price_id;
        $subscription->created_at = now();
        $subscription->renews_at = $plan->isYearly() ? now()->addYear() : now()->addMonthNoOverflow();
        $subscription->save();

        $subscription->items()->update([
            'plan' => $plan->payment_provider_price_id,
        ]);

        return $subscription;
    }

    public function cancelSubscription(): ?Subscription
    {
        return optional($this->getSubscription())->cancel();
    }

    public function cancelSubscriptionNow(): ?Subscription
    {
        return optional($this->getSubscription())->cancelNow();
    }

    public function resumeSubscription(): ?Subscription
    {
        return optional($this->getSubscription())->resume();
    }

    public function hasIncompleteSubscriptionPayment(): bool
    {
        return false; // Mollie handles this differently
    }

    public function latestSubscriptionPayment(): \Laravel\Paddle\Payment|\Laravel\Cashier\Payment|null
    {
        return null; // Mollie uses orders, not payments
    }
}
