<?php

namespace Opcodes\Spike\Paddle;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Laravel\Paddle\Cashier;
use Opcodes\Spike\Cart;
use Opcodes\Spike\Contracts\PaymentGatewayContract;
use Opcodes\Spike\Contracts\SpikeSubscription;
use Opcodes\Spike\PaymentProvider;
use Opcodes\Spike\SubscriptionPlan;
use Opcodes\Spike\Traits\ScopedToBillable;

class PaymentGateway implements PaymentGatewayContract
{
    use ScopedToBillable;

    static string $subscriptionName = 'default';

    public function provider(): PaymentProvider
    {
        return PaymentProvider::Paddle;
    }

    public function findBillable($customer_id)
    {
        return Cashier::findBillable($customer_id);
    }

    public static function allPrices(): Collection
    {
        return collect(Cashier::api('GET', 'prices')['data']);
    }

    /**
     * @throws \Laravel\Paddle\Exceptions\PaddleException
     */
    public function payForCart(Cart $cart): bool
    {
        $billable = $this->getBillable();

        $cartCurrency = $cart->validateAndDetermineCurrency();

        foreach ($cart->items as $item) {
            $product = $item->product();
            $billable->tabPrice($product->payment_provider_price_id, $item->quantity, [
                'currency' => $product->currency,
            ]);
        }

        $invoiceOptions = [
            'discounts' => $cart->hasPromotionCode() ? [
                [
                    'coupon' => $cart->promotionCode()->coupon()->id,
                ],
            ] : [],
        ];

        if ($cartCurrency) {
            $invoiceOptions['currency'] = $cartCurrency;
        }

        return (bool) $billable->invoice($invoiceOptions);
    }

    /**
     * @throws \Laravel\Paddle\Exceptions\PaddleException
     */
    public function invoiceAndPayItems(array $items, array $options = []): bool
    {
        throw new \RuntimeException('Offline transactions are not supported for Paddle.');
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
        return $this->getBillable()
            ->subscribed(self::$subscriptionName, optional($plan)->payment_provider_price_id);
    }

    /**
     * @throws \Laravel\Paddle\Exceptions\PaddleException
     */
    public function createSubscription(SubscriptionPlan $plan, bool $requirePaymentCard = true): Subscription
    {
        if ($requirePaymentCard) {
            $billable = $this->getBillable();

            $subscription = $billable
                ->newSubscription(self::$subscriptionName, $plan->payment_provider_price_id)
                ->add([], $billable->stripePromotionCode() ? [
                    'promotion_code' => $billable->stripePromotionCode()->id,
                ] : []);
            $subscription->forceFill(['promotion_code_id' => $billable->stripePromotionCode()->id ?? null])->save();

            /** @noinspection PhpIncompatibleReturnTypeInspection */
            return $subscription;
        }

        $subscription = $this->getBillable()->subscriptions()->create([
            'type' => self::$subscriptionName,
            'paddle_id' => '',
            'status' => Subscription::STATUS_ACTIVE,
            'ends_at' => null,
            'renews_at' => $plan->isYearly() ? now()->addYear() : now()->addMonthNoOverflow(),
        ]);

        $subscription->items()->create([
            'product_id' => '',
            'price_id' => $plan->payment_provider_price_id,
            'status' => Subscription::STATUS_ACTIVE,
            'quantity' => 1,
        ]);

        return $subscription;
    }

    /**
     * @throws \Laravel\Paddle\Exceptions\PaddleException
     */
    public function switchSubscription(SubscriptionPlan $plan, bool $requirePaymentCard = true): Subscription
    {
        $existingSubscription = $this->getSubscription();

        if ($requirePaymentCard && $existingSubscription->hasPaymentCard()) {
            return $this->getSubscription()->prorateImmediately()->swap($plan->payment_provider_price_id);
        }

        if ($requirePaymentCard && ! $existingSubscription->hasPaymentCard()) {
            $subscription = $this->createSubscription($plan, $requirePaymentCard);
            $existingSubscription->delete();

            return $subscription;
        }

        // without payment card, we need to manually update the subscription
        $subscription = $this->getSubscription();
        $subscription->created_at = now();
        $subscription->renews_at = $plan->isYearly() ? now()->addYear() : now()->addMonthNoOverflow();
        $subscription->save();

        $subscription->items()->update([
            'price_id' => $plan->payment_provider_price_id,
        ]);

        return $subscription->load('items');
    }

    public function cancelSubscription(): ?Subscription
    {
        return optional($this->getSubscription())->cancel();
    }

    public function cancelSubscriptionNow(): ?Subscription
    {
        return optional($this->getSubscription())->cancelNowAndInvoice();
    }

    public function resumeSubscription(): ?Subscription
    {
        return optional($this->getSubscription())->stopCancelation();
    }

    public function hasIncompleteSubscriptionPayment(): bool
    {
        return false;
    }

    public function latestSubscriptionPayment(): \Laravel\Paddle\Payment|\Laravel\Cashier\Payment|null
    {
        return null;
    }
}
