<?php

namespace Opcodes\Spike\Actions;

use Opcodes\Spike\Exceptions\MissingPaymentProviderException;
use Opcodes\Spike\Facades\Spike;
use Opcodes\Spike\Paddle\SpikeBillable as SpikeBillablePaddle;
use Opcodes\Spike\PaymentProvider;
use Opcodes\Spike\Stripe\SpikeBillable as SpikeBillableStripe;

class VerifyBillableUsesTrait
{
    /**
     * @throws MissingPaymentProviderException
     */
    public function handle($billable): void
    {
        $traitsUsed = trait_uses_recursive($billable);
        $paymentProvider = Spike::paymentProvider();

        if ($paymentProvider->isStripe() && !in_array(SpikeBillableStripe::class, $traitsUsed)) {
            throw new \InvalidArgumentException('['.get_class($billable).'] does not use the required '. SpikeBillableStripe::class.' trait.');
        } elseif ($paymentProvider->isPaddle() && !in_array(SpikeBillablePaddle::class, $traitsUsed)) {
            throw new \InvalidArgumentException('['.get_class($billable).'] does not use the required '. SpikeBillablePaddle::class.' trait.');
        } elseif ($paymentProvider === PaymentProvider::None) {
            throw new MissingPaymentProviderException();
        }
    }
}
