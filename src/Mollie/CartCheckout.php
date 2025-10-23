<?php

namespace Opcodes\Spike\Mollie;

use Illuminate\Support\Facades\Log;
use Opcodes\Spike\Cart;
use Opcodes\Spike\Contracts\SpikeBillable;

class CartCheckout
{
    /**
     * Create a Mollie checkout session for the cart
     *
     * @param Cart $cart
     * @param SpikeBillable $billable
     * @return string Checkout URL
     */
    public static function create(Cart $cart, SpikeBillable $billable): string
    {
        $cartCurrency = $cart->validateAndDetermineCurrency();

        $order = $billable->newOrder();

        foreach ($cart->items as $item) {
            $product = $item->product();
            $order->addItem([
                'description' => $product->name,
                'price' => $product->price_in_cents,
                'quantity' => $item->quantity,
                'currency' => $product->currency ?? config('cashier.currency', 'EUR'),
            ]);
        }

        // Add metadata for cart tracking
        $order->metadata = [
            'spike_cart_id' => $cart->id,
        ];

        if ($cart->hasPromotionCode()) {
            // TODO: Implement Mollie coupon/discount if supported
            Log::warning('[Spike\MollieCartCheckout] Promotion codes are not yet implemented for Mollie.');
        }

        // Set redirect URLs
        $order->redirectUrl(route('spike.mollie.checkout.success', ['cart' => $cart->id]));
        $order->webhookUrl(route('spike.mollie.webhook'));

        try {
            $processedOrder = $order->processPayment();

            if ($processedOrder && $processedOrder->checkout_url) {
                return $processedOrder->checkout_url;
            }

            throw new \Exception('Failed to create Mollie checkout session');
        } catch (\Exception $e) {
            Log::error('[Spike\MollieCartCheckout] Failed to create checkout session', [
                'cart_id' => $cart->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
