<?php

namespace Opcodes\Spike\Actions\Products;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Opcodes\Spike\Cart;

class StripeCheckoutRedirectWithLock
{
    public function handle(Cart $cart)
    {
        if (! $this->supportsLocks()) {
            // Locks not supported, just return early with the intended Checkout redirect.
            return $cart->stripeCheckout()->redirectToStripeCheckout();
        }

        $lock = Cache::lock('spike:purchase:lock', 10);

        if ($lock->block(10)) {
            // Just in case, reload the user/team data to ensure we have their stripe_id if set in a previous request.
            $cart->billable->refresh();

            $redirect = $cart->stripeCheckout()->redirectToStripeCheckout();

            $lock->release();
        } else {
            Log::warning('Could not acquire lock for products checkout. Redirecting back...');

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
