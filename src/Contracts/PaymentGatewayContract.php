<?php

namespace Opcodes\Spike\Contracts;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Opcodes\Spike\Cart;
use Opcodes\Spike\PaymentProvider;
use Opcodes\Spike\SubscriptionPlan;

interface PaymentGatewayContract
{
    public function provider(): PaymentProvider;

    /**
     * @param $customer_id
     * @return SpikeBillable
     */
    public function findBillable($customer_id);

    /**
     * @param SpikeBillable|null $billable
     * @return $this
     */
    public function billable($billable = null): static;

    /**
     * @return SpikeBillable|Model|null
     */
    public function getBillable();

    /**
     * @throws \Laravel\Cashier\Exceptions\IncompletePayment
     */
    public function payForCart(Cart $cart): bool;

    /**
     * @throws \Laravel\Cashier\Exceptions\IncompletePayment
     */
    public function invoiceAndPayItems(array $items, array $options = []): bool;

    public function getSubscription(): ?SpikeSubscription;

    public function getRenewalDate(): ?CarbonInterface;

    public function subscribed(?SubscriptionPlan $plan = null): bool;

    /**
     * @throws \Laravel\Cashier\Exceptions\IncompletePayment
     */
    public function createSubscription(SubscriptionPlan $plan, bool $requirePaymentCard = true): SpikeSubscription;

    /**
     * @throws \Laravel\Cashier\Exceptions\SubscriptionUpdateFailure
     * @throws \Laravel\Cashier\Exceptions\IncompletePayment
     */
    public function switchSubscription(SubscriptionPlan $plan, bool $requirePaymentCard = true): SpikeSubscription;

    public function cancelSubscription(): ?SpikeSubscription;

    public function cancelSubscriptionNow(): ?SpikeSubscription;

    public function resumeSubscription(): ?SpikeSubscription;

    public function hasIncompleteSubscriptionPayment(): bool;

    public function latestSubscriptionPayment(): \Laravel\Paddle\Payment|\Laravel\Cashier\Payment|null;
}
