<?php

namespace Opcodes\Spike\Actions\Products;

use Laravel\Cashier\Exceptions\IncompletePayment;
use Opcodes\Spike\Cart;
use Opcodes\Spike\Contracts\ProcessCartPaymentContract;

class ProcessCart
{
    public function __construct(
        protected ProcessCartPaymentContract $processCartPayment,
        protected ProvideCartProvidables $provideCartProvidables,
    ){}

    /**
     * @param Cart $cart
     * @return void
     * @throws IncompletePayment
     */
    public function execute(Cart $cart)
    {
        if ($cart->totalPriceInCents() > 0) {
            $this->processCartPayment->execute($cart);
        }

        $this->provideCartProvidables->handle($cart);
    }
}
