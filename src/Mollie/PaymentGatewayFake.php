<?php

namespace Opcodes\Spike\Mollie;

use Carbon\CarbonInterface;
use Opcodes\Spike\Cart;
use Opcodes\Spike\Contracts\PaymentGatewayContract;
use Opcodes\Spike\Contracts\SpikeSubscription;
use Opcodes\Spike\Facades\Spike;
use Opcodes\Spike\PaymentProvider;
use Opcodes\Spike\Product;
use Opcodes\Spike\SubscriptionPlan;
use Opcodes\Spike\Traits\ScopedToBillable;
use PHPUnit\Framework\Assert as PHPUnit;

class PaymentGatewayFake implements PaymentGatewayContract
{
    use ScopedToBillable;

    static string $subscriptionName = 'default';

    protected array $purchasedProducts = [];
    protected array $paidCarts = [];

    public function provider(): PaymentProvider
    {
        return PaymentProvider::Mollie;
    }

    public function findBillable($customer_id)
    {
        return null;
    }

    public function payForCart(Cart $cart): bool
    {
        foreach ($cart->items as $item) {
            if (!isset($this->purchasedProducts[$item->product_id])) {
                $this->purchasedProducts[$item->product_id] = 0;
            }

            $this->purchasedProducts[$item->product_id] += $item->quantity;
        }

        $this->paidCarts[] = $cart->id;

        return true;
    }

    public function invoiceAndPayItems(array $items, array $options = []): bool
    {
        return true;
    }

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
        $subscription = $this->getBillable()->subscriptions()->create([
            'type' => self::$subscriptionName,
            'mollie_subscription_id' => 'sub_fake_' . \Illuminate\Support\Str::random(16),
            'mollie_plan' => $plan->payment_provider_price_id,
            'plan' => $plan->payment_provider_price_id,
            'ends_at' => null,
            'renews_at' => $plan->isYearly() ? now()->addYear() : now()->addMonthNoOverflow(),
        ]);

        $subscription->items()->create([
            'mollie_subscription_id' => 'si_fake_' . \Illuminate\Support\Str::random(16),
            'plan' => $plan->payment_provider_price_id,
            'quantity' => 1,
        ]);

        return $subscription;
    }

    public function switchSubscription(SubscriptionPlan $plan, bool $requirePaymentCard = true): SpikeSubscription
    {
        $subscription = $this->getSubscription();
        $subscription->plan = $plan->payment_provider_price_id;
        $subscription->mollie_plan = $plan->payment_provider_price_id;
        $subscription->renews_at = $plan->isYearly() ? now()->addYear() : now()->addMonthNoOverflow();
        $subscription->save();

        $subscription->items()->update([
            'plan' => $plan->payment_provider_price_id,
        ]);

        return $subscription;
    }

    public function cancelSubscription(): ?SpikeSubscription
    {
        $subscription = $this->getSubscription();
        if ($subscription) {
            $subscription->ends_at = now()->addDays(30);
            $subscription->save();
        }
        return $subscription;
    }

    public function cancelSubscriptionNow(): ?SpikeSubscription
    {
        $subscription = $this->getSubscription();
        if ($subscription) {
            $subscription->ends_at = now();
            $subscription->save();
        }
        return $subscription;
    }

    public function resumeSubscription(): ?SpikeSubscription
    {
        $subscription = $this->getSubscription();
        if ($subscription) {
            $subscription->ends_at = null;
            $subscription->save();
        }
        return $subscription;
    }

    public function hasIncompleteSubscriptionPayment(): bool
    {
        return false;
    }

    public function latestSubscriptionPayment(): \Laravel\Paddle\Payment|\Laravel\Cashier\Payment|null
    {
        return null;
    }

    // Test assertion methods

    public function assertProductPurchased($product, $quantity = 1)
    {
        $product = $product instanceof Product ? $product->id : (string) $product;

        PHPUnit::assertTrue(
            isset($this->purchasedProducts[$product]),
            'The product was not purchased.'
        );

        PHPUnit::assertEquals(
            $quantity,
            $this->purchasedProducts[$product],
            'The product was purchased a different amount. '
                .'Expected '.$quantity.', but purchased '.$this->purchasedProducts[$product]
        );
    }

    public function assertNothingPurchased()
    {
        PHPUnit::assertEmpty(
            $this->purchasedProducts,
            'Expected nothing to be purchased, but some products were paid for.'
        );
    }

    public function assertCartPaid(Cart $cart)
    {
        PHPUnit::assertTrue(
            $cart->fresh()->paid() && in_array($cart->id, $this->paidCarts),
            'The cart was not paid.',
        );
    }

    public function assertCartNotPaid(Cart $cart)
    {
        PHPUnit::assertFalse(
            $cart->fresh()->paid(),
            'The cart was marked as paid, when expected it not to be paid.'
        );

        PHPUnit::assertFalse(
            in_array($cart->id, $this->paidCarts),
            'The cart was actually paid, when expected it not to be paid.'
        );
    }

    public function assertSubscribed($plan_or_price_id = null): void
    {
        if (is_string($plan_or_price_id)) {
            $plan_or_price_id = $this->findSubscriptionPlan($plan_or_price_id);
        }

        PHPUnit::assertTrue(
            $this->subscribed($plan_or_price_id),
            'Expected to be subscribed, but a subscription was not found.'
        );
    }

    public function assertNotSubscribed($plan_or_price_id = null): void
    {
        if (is_string($plan_or_price_id)) {
            $plan_or_price_id = $this->findSubscriptionPlan($plan_or_price_id);
        }

        PHPUnit::assertFalse(
            $this->subscribed($plan_or_price_id),
            'Expected to not be subscribed, but a subscription was found.'
        );
    }

    public function assertSubscriptionCancelled(): void
    {
        $subscription = $this->getSubscription();

        PHPUnit::assertTrue(
            !empty($subscription) && !empty($subscription->ends_at),
            'Subscription was not cancelled or not found.'
        );
    }

    public function assertSubscriptionActive(): void
    {
        $subscription = $this->getSubscription();

        PHPUnit::assertTrue(
            !empty($subscription) && empty($subscription->ends_at),
            'Subscription is is cancelled or not found.'
        );
    }

    public function setRenewalDate(?CarbonInterface $date)
    {
        // For testing purposes - this would need to be implemented
        // by modifying the subscription's renews_at field
    }

    protected function findSubscriptionPlan(string $plan_or_price_id): ?SubscriptionPlan
    {
        return Spike::findSubscriptionPlan($plan_or_price_id, $this->getBillable());
    }
}
