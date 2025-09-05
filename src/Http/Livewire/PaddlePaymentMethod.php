<?php

namespace Opcodes\Spike\Http\Livewire;

use Livewire\Component;
use Opcodes\Spike\Facades\Spike;
use Opcodes\Spike\Paddle\PaymentMethod;
use Opcodes\Spike\Paddle\Subscription;
use Opcodes\Spike\Paddle\Transaction;

class PaddlePaymentMethod extends Component
{
    public bool $shouldLoadPaymentMethod = false;

    public function render()
    {
        if ($this->shouldLoadPaymentMethod) {
            list($transactionId, $paymentMethod) = $this->getPaymentMethod();
        }

        return view('spike::livewire.paddle-payment-method', [
            'transactionId' => $transactionId ?? null,
            'paymentMethod' => $paymentMethod ?? null,
        ]);
    }

    public function loadPaymentMethod()
    {
        $this->shouldLoadPaymentMethod = true;
    }

    private function getPaymentMethod(): array
    {
        $billable = Spike::resolve();

        /** @var Subscription $subscription */
        $subscription = $billable->getSubscription();

        if (! $subscription || empty($subscription->paddle_id)) return [null, null];

        /** @var Transaction $transaction */
        $transaction = $subscription->transactions()->latest('id')->first();
        $newPaymentMethodTransaction = $subscription->paymentMethodUpdateTransaction();

        return [
            $newPaymentMethodTransaction['id'],
            $transaction->getPaymentMethodUsed(),
        ];
    }
}
