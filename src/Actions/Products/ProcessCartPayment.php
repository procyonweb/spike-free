<?php

namespace Opcodes\Spike\Actions\Products;

use Opcodes\Spike\Cart;
use Opcodes\Spike\Contracts\ProcessCartPaymentContract;
use Opcodes\Spike\Facades\PaymentGateway;

class ProcessCartPayment implements ProcessCartPaymentContract
{
    /**
     * @throws \Laravel\Cashier\Exceptions\IncompletePayment
     */
    public function execute(Cart $cart): bool
    {
        if ($cart->paid() || $cart->empty()) {
            return false;
        }

        if (PaymentGateway::billable($cart->billable)->payForCart($cart)) {
            $cart->update(['paid_at' => now()]);

            return true;
        }

        return false;
    }
}
