<?php

namespace Opcodes\Spike\Livewire;

use Livewire\Component;
use Opcodes\Spike\Cart;
use Opcodes\Spike\Facades\Spike;

class PaddleCheckoutButton extends Component
{
    public function render()
    {
        return view('spike::livewire.paddle-checkout-button', [
            'paddleCheckout' => $this->getPaddleCheckoutObject(),
        ]);
    }

    protected function cart(): Cart
    {
        return Cart::forBillable(Spike::resolve());
    }

    private function getPaddleCheckoutObject(): ?\Laravel\Paddle\Checkout
    {
        if (! Spike::paymentProvider()->isPaddle()) {
            return null;
        }

        return $this->cart()->paddleCheckout()->paddleCheckoutObject();
    }
}
