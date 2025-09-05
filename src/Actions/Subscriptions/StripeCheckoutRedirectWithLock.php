<?php

namespace Opcodes\Spike\Actions\Subscriptions;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Opcodes\Spike\Facades\Spike;
use Opcodes\Spike\Stripe\Interfaces\SubscriptionCheckoutRedirectInterface;

class StripeCheckoutRedirectWithLock
{
    public function handle($preselectedPlan)
    {
        if (! $this->supportsLocks()) {
            // Locks not supported, just return early with the intended Checkout redirect.
            return app(SubscriptionCheckoutRedirectInterface::class)->handle($preselectedPlan);
        }

        $lock = Cache::lock('spike:subscribe:lock', 10);

        if ($lock->block(10)) {
            // Just in case, reload the user/team data to ensure we have their stripe_id if set in a previous request.
            Spike::resolve()->refresh();

            $redirect = app(SubscriptionCheckoutRedirectInterface::class)->handle($preselectedPlan);

            $lock->release();
        } else {
            Log::warning('Could not acquire lock for subscription checkout. Redirecting back...');

            $redirect = redirect()->back();
        }

        return $redirect;
    }

    protected function supportsLocks(): bool
    {
        $store = Cache::store()->getStore();

        return $store instanceof \Illuminate\Contracts\Cache\LockProvider;
    }
}
