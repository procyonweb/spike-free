<?php

namespace Opcodes\Spike\Paddle;

use Laravel\Paddle\Checkout;
use Opcodes\Spike\Cart;
use Opcodes\Spike\CartItem;

class CartCheckout
{
    public function __construct(
        protected Cart $cart
    )
    {
    }

    public function paddleCheckoutObject(array $customData = [], string|null $returnUrl = null): Checkout
    {
        $prices = $this->cart->items
            ->mapWithKeys(function (CartItem $item) {
                return [$item->product()->payment_provider_price_id => $item->quantity];
            })->toArray();

        $checkout = $this->cart->billable->checkout($prices)
            ->customData(array_merge([
                'spike_cart_id' => $this->cart->id,
            ], $customData));

        if (config('spike.path')) {
            $returnUrl = $returnUrl ?? route('spike.purchase.validate-cart', ['cart' => $this->cart->id]);
        }

        if ($returnUrl) {
            $checkout = $checkout->returnTo($returnUrl);
        }

        return $checkout;
    }
}
