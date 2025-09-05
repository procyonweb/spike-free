<?php

namespace Opcodes\Spike\Console\Commands;

use Opcodes\Spike\Actions\Products\ProvideCartProvidables;
use Opcodes\Spike\Actions\Subscriptions\ProvideSubscriptionPlanMonthlyProvides;
use Opcodes\Spike\Cart;
use Opcodes\Spike\Contracts\SpikeSubscription;
use Opcodes\Spike\Contracts\SpikeSubscriptionItem;
use Opcodes\Spike\Facades\Spike;
use Opcodes\Spike\Stripe\Subscription as StripeSubscription;
use Opcodes\Spike\Paddle\Subscription as PaddleSubscription;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Opcodes\Spike\Facades\PaymentGateway;
use Opcodes\Spike\PaymentProvider;

class SyncProvidablesCommand extends Command
{
    protected $signature = 'spike:sync-providables {--subscription= : The subscription ID to sync provides for} {--cart= : The cart ID to sync provides for}';

    protected $description = 'Sync providables for a specific subscription or product cart.';

    public function handle(): void
    {
        $subscriptionId = intval($this->option('subscription'));
        $cartId = intval($this->option('cart'));

        if (empty($subscriptionId) && empty($cartId)) {
            $this->warn('Please provide either a subscription ID or a cart ID using the --subscription or --cart options respectively.');
            return;
        }

        if (! empty($subscriptionId)) {
            $this->syncSubscriptionProvides($subscriptionId);
        }

        if (! empty($cartId)) {
            $this->syncCartProvides($cartId);
        }
    }

    private function syncSubscriptionProvides(int $subscriptionId): void
    {
        /** @var SpikeSubscription $subscription */
        $subscription = match (PaymentGateway::provider()) {
            PaymentProvider::Stripe => StripeSubscription::find($subscriptionId),
            PaymentProvider::Paddle => PaddleSubscription::find($subscriptionId),
            default => throw new \Exception('Payment provider is not configured. Please run `php artisan spike:install`.'),
        };

        if (! $subscription) {
            $this->error('Could not find subscription with ID ' . $subscriptionId);
            return;
        }

        if (! $subscription->active()) {
            $this->error('Subscription is not active.');
            return;
        }

        /** @var SpikeSubscriptionItem $item */
        $item = $subscription->items()->first();
        /** @var SpikeBillable|Model $billable */
        $billable = $subscription->getBillable();

        $plan = Spike::findSubscriptionPlan($item->getPriceId(), $billable);

        if (! $plan) {
            $this->error('Could not resolve the subscription plan with price ID ' . $item->getPriceId());
            return;
        }

        foreach ($subscription->items as $subscriptionItem) {
            app(ProvideSubscriptionPlanMonthlyProvides::class)
                ->handle($plan, $billable, $subscriptionItem);
        }

        $this->info('Successfully synced provides for subscription ID ' . $subscriptionId . '.');
    }

    private function syncCartProvides(int $cartId): void
    {
        $cart = Cart::find($cartId);

        if (! $cart) {
            $this->error('Could not find cart with ID ' . $cartId);
            return;
        }

        if (! $cart->paid()) {
            $this->error('Cart is not paid.');
            return;
        }

        app(ProvideCartProvidables::class)->handle($cart);

        $this->info('Successfully synced provides for cart ID ' . $cartId . '.');
    }
}
