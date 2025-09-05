<?php

namespace Opcodes\Spike\Paddle;

class Transaction extends \Laravel\Paddle\Transaction
{
    protected $table = 'paddle_transactions';

    public function getPaymentMethodUsed(): ?PaymentMethod
    {
        return PaymentMethod::fromPaddleTransaction(
            $this->asPaddleTransaction()
        );
    }
}
