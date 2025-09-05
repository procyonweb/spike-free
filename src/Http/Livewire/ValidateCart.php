<?php

namespace Opcodes\Spike\Http\Livewire;

use Livewire\Component;
use Opcodes\Spike\Cart;

class ValidateCart extends Component
{
    public Cart $cart;

    public function mount(Cart $cart)
    {
        $this->cart = $cart;
    }

    public function render()
    {
        return view('spike::livewire.validate-cart');
    }

    public function checkStatus()
    {
        $this->cart->syncWithStripeCheckoutSession();

        // Paddle is checked and in the webhook handler

        if ($this->cart->paid()) {
            return to_route('spike.purchase.thank-you', ['cart' => $this->cart->id]);
        }

        return null;
    }
}
