<?php

namespace Opcodes\Spike\Http\Livewire;

use Livewire\Component;
use Opcodes\Spike\Facades\Spike;

class StripePaymentMethods extends Component
{
    public array $paymentMethods = [];
    public bool $paymentMethodsLoaded = false;
    public ?string $defaultPaymentMethodId;

    protected $listeners = [
        'paymentMethodAdded' => 'loadPaymentMethods',
    ];

    public function render()
    {
        return view('spike::livewire.stripe-payment-methods');
    }

    public function loadPaymentMethods()
    {
        $billable = Spike::resolve();
        $this->paymentMethods = $billable->paymentMethods()->toArray();
        $this->defaultPaymentMethodId = $billable->defaultPaymentMethod()?->id;
        $this->paymentMethodsLoaded = true;
    }

    public function setDefaultMethod($paymentMethodId)
    {
        $billable = Spike::resolve();

        $billable->updateDefaultPaymentMethod($paymentMethodId);
        $this->defaultPaymentMethodId = $billable->defaultPaymentMethod()?->id;
    }

    public function deletePaymentMethod($paymentMethodId)
    {
        $billable = Spike::resolve();

        $billable->deletePaymentMethod($paymentMethodId);
        $this->loadPaymentMethods();
    }
}
