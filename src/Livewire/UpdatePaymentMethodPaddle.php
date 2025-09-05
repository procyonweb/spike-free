<?php

namespace Opcodes\Spike\Livewire;

use Livewire\Component;
use Opcodes\Spike\Paddle\Subscription;

class UpdatePaymentMethodPaddle extends Component
{
    public Subscription $subscription;

    public function mount(Subscription $subscription)
    {
        $this->subscription = $subscription;
    }

    public function render()
    {
        return view('spike::livewire.update-payment-method-paddle', [
            'transactionId' => $this->subscription->paymentMethodUpdateTransaction()['id'],
        ]);
    }
}
