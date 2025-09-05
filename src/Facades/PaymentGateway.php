<?php

namespace Opcodes\Spike\Facades;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;
use Opcodes\Spike\Cart;
use Opcodes\Spike\Contracts\PaymentGatewayContract;
use Opcodes\Spike\Contracts\SpikeBillable;
use Opcodes\Spike\Contracts\SpikeSubscription;
use Opcodes\Spike\PaymentProvider;
use Opcodes\Spike\SubscriptionPlan;

/**
 * @method static PaymentProvider provider()
 *
 * @method static SpikeBillable|null findBillable($customer_id)
 * @method static PaymentGatewayContract billable(SpikeBillable $billable)
 * @method static SpikeBillable|Model|null getBillable()
 *
 * @method static bool payForCart(Cart $cart)
 * @method static bool invoiceAndPayItems(array $items, array $options = [])
 *
 * @method static SpikeSubscription getSubscription()
 * @method static CarbonInterface|null getRenewalDate()
 * @method static bool subscribed(SubscriptionPlan|null $plan = null)
 * @method static SpikeSubscription|null createSubscription(SubscriptionPlan $plan, bool $requirePaymentCard = true)
 * @method static SpikeSubscription|null cancelSubscription()
 * @method static SpikeSubscription|null cancelSubscriptionNow()
 * @method static SpikeSubscription|null resumeSubscription()
 * @method static SpikeSubscription|null switchSubscription(SubscriptionPlan $plan, bool $requirePaymentCard = true)
 * @method static bool hasIncompleteSubscriptionPayment()
 * @method static \Laravel\Paddle\Payment|\Laravel\Cashier\Payment|null latestSubscriptionPayment()
 *
 * // Testing
 * @method static void assertSubscribed(SubscriptionPlan|string|null $plan_or_price_id = null)
 * @method static void assertNotSubscribed(SubscriptionPlan|string|null $plan_or_price_id = null)
 * @method static void assertSubscriptionCancelled()
 * @method static void assertSubscriptionActive()
 *
 * @method static void assertProductPurchased($product, int $quantity = 1)
 * @method static void assertCartPaid(Cart $cart)
 * @method static void assertCartNotPaid(Cart $cart)
 * @method static void assertNothingPurchased()
 * @method static void setRenewalDate(CarbonInterface $date)
 *
 * @see \Opcodes\Spike\Stripe\PaymentGateway
 */
class PaymentGateway extends Facade
{
    public static function fake()
    {
        static::swap($fake = match (Spike::paymentProvider()) {
            PaymentProvider::Paddle => new \Opcodes\Spike\Paddle\PaymentGatewayFake(),
            default => new \Opcodes\Spike\Stripe\PaymentGatewayFake(),
        });

        return $fake;
    }

    protected static function getFacadeAccessor()
    {
        return 'spike.payment-gateway';
    }
}
