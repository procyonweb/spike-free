<?php

namespace Opcodes\Spike\Stripe;

use Opcodes\Spike\Cart;
use Opcodes\Spike\Product;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;
use Laravel\Cashier\Coupon;
use Opcodes\Spike\Facades\Spike;
use Opcodes\Spike\SubscriptionPlan;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Assert as PHPUnit;

class PaymentGatewayFake extends PaymentGateway
{
    protected array $purchasedProducts = [];
    protected array $paidCarts = [];
    protected ?CarbonInterface $renewalDate = null;
    protected ?string $promotionCodeId = null;
    protected ?string $couponId = null;
    protected bool $hasIncompletePayment = false;
    protected ?\Laravel\Cashier\Payment $latestPayment = null;
    protected array $validCouponPrices = [];
    protected array $promotionCodes = [];
    protected array $couponCodes = [];

    public function stripe()
    {
        throw new \RuntimeException('Stripe is not available in the fake payment gateway. Make sure you have all the necessary methods faked.');
    }

    /**
     * @param \Opcodes\Spike\Contracts\SpikeBillable|Model|null $billable
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

    public function applyPromotionCode(string $promo_code_id): void
    {
        $this->promotionCodeId = $promo_code_id;
    }

    public function applyCoupon(string $coupon_id): void
    {
        $this->couponId = $coupon_id;
    }

    public function assertPromotionCodeApplied(string $promo_code_id): void
    {
        PHPUnit::assertEquals(
            $promo_code_id,
            $this->promotionCodeId,
            'Expected promotion code was not applied.'
        );
    }

    public function assertCouponApplied(string $coupon_id): void
    {
        PHPUnit::assertEquals(
            $coupon_id,
            $this->couponId,
            'Expected coupon was not applied.'
        );
    }

    public function hasIncompleteSubscriptionPayment(): bool
    {
        return $this->hasIncompletePayment;
    }

    public function setHasIncompletePayment(bool $hasIncomplete): void
    {
        $this->hasIncompletePayment = $hasIncomplete;
    }

    public function latestSubscriptionPayment(): ?\Laravel\Cashier\Payment
    {
        return $this->latestPayment;
    }

    public function setLatestPayment(?\Laravel\Cashier\Payment $payment): void
    {
        $this->latestPayment = $payment;
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
            $plan = $this->findSubscriptionPlan($subscription->stripe_price);

            return $plan->isYearly()
                ? $subscription->created_at->copy()->addYear()
                : $subscription->created_at->copy()->addMonthNoOverflow();
        }

        return $subscription->renews_at ?? $subscription->created_at->copy()->addMonthNoOverflow();
    }

    public function createSubscription(SubscriptionPlan $plan, bool $requirePaymentCard = true): Subscription
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
        $subscription->update([
            'stripe_status' => \Stripe\Subscription::STATUS_CANCELED,
            'ends_at' => now(),
        ]);

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
        $subscription = $this->getSubscription()->fresh();

        $subscription->update([
            'stripe_status' => \Stripe\Subscription::STATUS_ACTIVE,
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

        $subscription->unsetRelation('items');

        return $subscription;
    }

    public function assertSubscribed($plan_or_price_id = null): void
    {
        if (is_string($plan_or_price_id)) {
            $plan_or_price_id = $this->findSubscriptionPlan($plan_or_price_id);
        }

        PHPUnit::assertTrue(
            $this->subscribed($plan_or_price_id),
            'Expected to be subscribed, but a subscription was not found, or not matching the expected plan.'
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

    public function isCouponValidForPrice(Coupon $coupon, string $priceId = null): bool
    {
        if (empty($priceId)) {
            return false;
        }

        return isset($this->validCouponPrices[$coupon->id]) &&
               in_array($priceId, $this->validCouponPrices[$coupon->id]);
    }

    public function setValidCouponPrice(Coupon $coupon, string $priceId): void
    {
        if (!isset($this->validCouponPrices[$coupon->id])) {
            $this->validCouponPrices[$coupon->id] = [];
        }
        $this->validCouponPrices[$coupon->id][] = $priceId;
    }

    public function assertCouponValidForPrice(Coupon $coupon, string $priceId): void
    {
        PHPUnit::assertTrue(
            $this->isCouponValidForPrice($coupon, $priceId),
            "Expected coupon {$coupon->id} to be valid for price {$priceId}."
        );
    }

    public function assertCouponInvalidForPrice(Coupon $coupon, string $priceId): void
    {
        PHPUnit::assertFalse(
            $this->isCouponValidForPrice($coupon, $priceId),
            "Expected coupon {$coupon->id} to be invalid for price {$priceId}."
        );
    }

    public function findStripePromotionCode(?string $promo_code_id = null): ?\Laravel\Cashier\PromotionCode
    {
        if (is_null($promo_code_id)) {
            return null;
        }

        return $this->promotionCodes[$promo_code_id] ?? null;
    }

    public function findStripeCouponCode(?string $coupon_code_id = null): ?\Laravel\Cashier\Coupon
    {
        if (is_null($coupon_code_id)) {
            return null;
        }

        return $this->couponCodes[$coupon_code_id] ?? null;
    }

    public function setPromotionCode(string $promo_code_id, ?\Laravel\Cashier\PromotionCode $promotionCode): void
    {
        $this->promotionCodes[$promo_code_id] = $promotionCode;
    }

    public function setCouponCode(string $coupon_code_id, ?\Laravel\Cashier\Coupon $coupon): void
    {
        $this->couponCodes[$coupon_code_id] = $coupon;
    }
}
