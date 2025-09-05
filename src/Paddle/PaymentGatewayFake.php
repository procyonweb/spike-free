<?php

namespace Opcodes\Spike\Paddle;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Opcodes\Spike\Cart;
use Opcodes\Spike\Contracts\SpikeSubscription;
use Opcodes\Spike\Facades\Spike;
use Opcodes\Spike\PaymentProvider;
use Opcodes\Spike\Product;
use Opcodes\Spike\SubscriptionPlan;
use PHPUnit\Framework\Assert as PHPUnit;

class PaymentGatewayFake extends PaymentGateway
{
    protected array $purchasedProducts = [];
    protected array $paidCarts = [];
    protected ?CarbonInterface $renewalDate = null;

    /**
     * @param SpikeBillable|Model|null $billable
     * @return $this
     */
    public function billable($billable = null): static
    {
        $this->billable = $billable;

        return $this;
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

    public function setRenewalDate(?CarbonInterface $date)
    {
        $this->renewalDate = $date;
    }

    public function getRenewalDate(): ?CarbonInterface
    {
        if (isset($this->renewalDate)) return $this->renewalDate;

        $subscription = $this->getSubscription();

        if (!$subscription || !$subscription->valid()) {
            return null;
        }

        if ($subscription->onGracePeriod()) {
            return $subscription->ends_at;
        }

        if ($subscription->hasPaymentCard()) {
            $plan = $this->findSubscriptionPlan($subscription->getPriceId());

            return $plan->isYearly()
                ? $subscription->created_at->copy()->addYear()
                : $subscription->created_at->copy()->addMonthNoOverflow();
        }

        return $subscription->renews_at ?? $subscription->created_at->copy()->addMonthNoOverflow();
    }

    public function createSubscription(SubscriptionPlan $plan, bool $requirePaymentCard = true): Subscription
    {
        return match (Spike::paymentProvider()) {
            PaymentProvider::Paddle => $this->createPaddleSubscription($plan),
            default => $this->createStripeSubscription($plan),
        };
    }

    private function createStripeSubscription(SubscriptionPlan $plan): Subscription
    {
        /** @var Subscription $subscription */
        $subscription = Subscription::factory()->create([
            'user_id' => $this->getBillable()->getKey(),
            'stripe_price' => $plan->payment_provider_price_id,
            'quantity' => 1,
        ]);

        $item = SubscriptionItem::factory()->create([
            'stripe_subscription_id' => $subscription->id,
            'stripe_product' => 'product_'.strtolower($plan->name),
            'stripe_price' => $plan->payment_provider_price_id,
            'quantity' => 1,
        ]);

        return $subscription;
    }

    private function createPaddleSubscription(SubscriptionPlan $plan): Subscription
    {
        /** @var Subscription $subscription */
        $subscription = Subscription::factory()->create([
            'billable_id' => $this->getBillable()->getKey(),
            'billable_type' => $this->getBillable()->getMorphClass(),
            'status' => 'active',
        ]);

        $item = SubscriptionItem::factory()->create([
            'paddle_subscription_id' => $subscription->id,
            'price_id' => $plan->payment_provider_price_id,
            'quantity' => 1,
        ]);

        return $subscription;
    }

    public function cancelSubscription(): ?Subscription
    {
        $subscription = $this->getSubscription();
        $subscription->update([
            'ends_at' => $subscription->created_at->copy()->addMonthNoOverflow()
        ]);

        return $subscription;
    }

    public function cancelSubscriptionNow(): ?Subscription
    {
        $subscription = $this->getSubscription();
        $subscription->update(['ends_at' => now()]);

        if (Spike::paymentProvider() === PaymentProvider::Stripe) {
            $subscription->update(['stripe_status' => \Stripe\Subscription::STATUS_CANCELED]);
        } elseif (Spike::paymentProvider() === PaymentProvider::Paddle) {
            $subscription->update(['status' => \Laravel\Paddle\Subscription::STATUS_CANCELED]);
        }

        return $subscription;
    }

    public function resumeSubscription(): ?Subscription
    {
        $subscription = $this->getSubscription();
        $subscription->update(['ends_at' => null]);

        return $subscription;
    }

    public function switchSubscription(SubscriptionPlan $plan, bool $requirePaymentCard = true): Subscription
    {
        $subscription = $this->getSubscription();

        match (Spike::paymentProvider()) {
            PaymentProvider::Paddle => $this->switchSubscriptionPaddle($subscription, $plan),
            default => $this->switchSubscriptionStripe($subscription, $plan),
        };

        $subscription->unsetRelation('items');

        return $subscription;
    }

    private function switchSubscriptionStripe(SpikeSubscription $subscription, SubscriptionPlan $plan): void
    {
        $subscription->update([
            'stripe_price' => $plan->payment_provider_price_id,
            'quantity' => 1,
        ]);

        $subscription->items()->create([
            'stripe_id' => 'si_'.Str::random(10),
            'stripe_product' => 'product_'.strtolower($plan->name),
            'stripe_price' => $plan->payment_provider_price_id,
            'quantity' => 1,
        ]);

        // Delete items that aren't attached to the subscription anymore...
        $subscription->items()->where('stripe_price', '!=', $plan->payment_provider_price_id)->delete();
    }

    private function switchSubscriptionPaddle(SpikeSubscription $subscription, SubscriptionPlan $plan): void
    {
        $subscription->items()->create([
            'product_id' => 'prod_'.Str::random(26),
            'price_id' => $plan->payment_provider_price_id,
            'quantity' => 1,
        ]);

        // Delete items that aren't attached to the subscription anymore...
        $subscription->items()->where('price_id', '!=', $plan->payment_provider_price_id)->delete();
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

    protected function findSubscriptionPlan(string $plan_or_price_id): ?SubscriptionPlan
    {
        return Spike::findSubscriptionPlan($plan_or_price_id, $this->getBillable());
    }
}
