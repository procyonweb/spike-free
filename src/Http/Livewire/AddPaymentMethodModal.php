<?php

namespace Opcodes\Spike\Http\Livewire;

use Opcodes\Spike\Facades\Spike;
use Livewire\Component;
use LivewireUI\Modal\ModalComponent;
use Stripe\SetupIntent;

class AddPaymentMethodModal extends ModalComponent
{
    public ?string $stripeIntentClientSecret = null;

    public function render()
    {
        return view('spike::livewire.add-payment-method-modal');
    }

    public function loadStripe()
    {
        Spike::resolve()->createOrGetStripeCustomer();

        $billable = Spike::resolve();

        $stripeIntent = $billable->createSetupIntent();
        $this->stripeIntentClientSecret = $stripeIntent->client_secret;
        $this->dispatch('initStripeElement');
    }

    public function addPaymentMethod($method)
    {
        $billable = Spike::resolve();

        $billable->addPaymentMethod($method);
        $billable->updateDefaultPaymentMethod($method);

        $this->dispatch('closeModal');
        $this->dispatch('paymentMethodAdded');
    }

    public static function closeModalOnClickAway(): bool
    {
        return false;
    }

    public static function destroyOnClose(): bool
    {
        return false;
    }

    public static function modalMaxWidth(): string
    {
        return '2xl';
    }
}
