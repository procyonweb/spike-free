<?php

namespace Opcodes\Spike\Http\Livewire;

use Illuminate\Http\Request;
use Livewire\Component;
use Opcodes\Spike\Actions\Products\StripeCheckoutRedirectWithLock;
use Opcodes\Spike\Cart;
use Opcodes\Spike\Exceptions\MissingPaymentProviderException;
use Opcodes\Spike\Facades\Spike;
use Opcodes\Spike\PaymentProvider;

class PurchaseCredits extends Component
{
    protected bool $payNow = false;

    protected ?string $errorMessage = null;

    protected $listeners = [
        'cartUpdated' => '$refresh',
        'checkoutClosed' => '$refresh',
    ];

    public function mount(Request $request)
    {
        $this->payNow = $request->boolean('pay', false);
    }

    public function render()
    {
        $errorMessage = $this->errorMessage;
        $this->errorMessage = null;

        return view('spike::livewire.purchase-credits', [
            'products' => Spike::products(),
            'cart' => $this->cart(),
            'payNow' => $this->payNow,
            'errorMessage' => $errorMessage,
        ]);
    }

    public function addProduct($id)
    {
        try {
            $this->cart()->addProduct($id);
            $this->dispatch('cartUpdated');
        } catch (\InvalidArgumentException $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function removeProduct($id)
    {
        $this->cart()->removeProduct($id);
        $this->dispatch('cartUpdated');
    }

    public function checkout()
    {
        return match (Spike::paymentProvider()) {
            PaymentProvider::Stripe => $this->checkoutStripe(),
            PaymentProvider::Paddle => $this->checkoutPaddle(),
            default => throw new MissingPaymentProviderException(),
        };
    }

    protected function checkoutStripe()
    {
        if (Spike::stripeCheckoutEnabled()) {
            return app(StripeCheckoutRedirectWithLock::class)->handle($this->cart());
        } else {
            $this->dispatch('openModal', component: 'spike::checkout');
        }

        return null;
    }

    protected function checkoutPaddle()
    {
        $this->dispatch('openModal', component: 'spike::checkout');

        return null;
    }

    protected function cart(): Cart
    {
        return Cart::forBillable(Spike::resolve());
    }
}
