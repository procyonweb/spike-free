<?php

namespace Opcodes\Spike\Facades;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use Laravel\Cashier\PromotionCode;
use Opcodes\Spike\PaymentProvider;
use Opcodes\Spike\Product;
use Opcodes\Spike\SpikeManager;
use Opcodes\Spike\SpikeManagerFake;
use Opcodes\Spike\Contracts\SpikeBillable;
use Opcodes\Spike\SubscriptionPlan;

/**
 * @see \Opcodes\Spike\SpikeManager
 *
 * @method static PaymentProvider paymentProvider()
 * @method static bool hasResolver(string|null $billableClass = null)
 * @method static SpikeBillable|Model|null resolve(callable|null $callback = null)
 * @method static void authorize(callable|null $callback = null)
 *
 * @method static SpikeManager billable($billable)
 * @method static SpikeBillable|Model|null getBillable()
 *
 * @method static void resolveAvatarUsing(callable $callback)
 * @method static string|null resolveAvatar(SpikeBillable|Model|null $billableInstance = null)
 *
 * @method static Collection|Product[] products(SpikeBillable|Model|null $billableInstance = null, bool $includeArchived = false)
 * @method static Product|null findProduct($id, SpikeBillable|Model|null $billableInstance = null, bool $includeArchived = false)
 * @method static bool productsAvailable(SpikeBillable|Model|null $billableInstance = null)
 * @method static void resolveProductsUsing(callable $callback)
 * @method static void redirectAfterProductPurchaseTo(string|callable $url, int $delayInSeconds = 0)
 * @method static void redirectAfterSubscriptionTo(string|callable $url, int $delayInSeconds = 0)
 *
 * @method static void processCartPaymentUsing($callback)
 *
 * @method static SubscriptionPlan[]|Collection subscriptionPlans(SpikeBillable|Model|null $billableInstance = null, bool $includeArchived = false)
 * @method static SubscriptionPlan[]|Collection monthlySubscriptionPlans(SpikeBillable|Model|null $billableInstance = null, bool $includeArchived = false)
 * @method static SubscriptionPlan[]|Collection yearlySubscriptionPlans(SpikeBillable|Model|null $billableInstance = null, bool $includeArchived = false)
 * @method static SubscriptionPlan|null findSubscriptionPlan(string $payment_provider_price_id, SpikeBillable|Model|null $billableInstance = null)
 * @method static SubscriptionPlan|null currentSubscriptionPlan(SpikeBillable|Model|null $billableInstance = null)
 * @method static bool subscriptionPlansAvailable(SpikeBillable|Model|null $billableInstance = null)
 * @method static void resolveSubscriptionPlansUsing(callable $callback)
 * @method static array|null mandateDataFromRequest()
 *
 * // testing only:
 * @method static void setProducts(array $products)
 * @method static void setSubscriptionPlans(array $plans)
 * @method static void clearCustomResolvers()
 */
class Spike extends Facade
{
    public static function fake()
    {
        static::swap($fake = new SpikeManagerFake());

        return $fake;
    }

    protected static function getFacadeAccessor()
    {
        return 'spike';
    }
}
