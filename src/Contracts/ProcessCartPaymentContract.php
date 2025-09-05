<?php

namespace Opcodes\Spike\Contracts;

use Opcodes\Spike\Cart;

interface ProcessCartPaymentContract
{
    /**
     * Process the payment for the cart and return true if successful.
     *
     * @param Cart $cart
     * @return bool
     */
    public function execute(Cart $cart): bool;
}
