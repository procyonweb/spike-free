<?php

namespace Opcodes\Spike\Http\Livewire;

use Laravel\Cashier\Exceptions\IncompletePayment;
use LivewireUI\Modal\ModalComponent;
use Opcodes\Spike\Actions\Products\ProcessCart;
use Opcodes\Spike\Cart;
use Opcodes\Spike\Facades\Spike;

class CheckoutModal extends ModalComponent
{
    public bool $loadPaymentMethod = false;
    public int $cartId;
    public string $discount_code_error = '';

    protected Cart $_cart;

    protected $listeners = [
        'paymentMethodAdded' => '$refresh',
    ];

    public function render()
    {
        $billable = Spike::resolve();

        return view('spike::livewire.checkout-modal', [
            'cart' => $this->cart(),
            'paymentMethod' => $this->loadPaymentMethod ? $billable->defaultPaymentMethod() : null,
        ]);
    }

    public function removeProductCompletely($id)
    {
        $this->cart()->removeProductCompletely($id);
        $this->dispatch('cartUpdated');
    }

    public function pay()
    {
        $cart = $this->cart();

        try {
            app(ProcessCart::class)->execute($cart);

        } catch (IncompletePayment $exception) {
            return redirect()->route(
                'cashier.payment',
                [
                    $exception->payment->id,
                    'redirect' => route('spike.purchase.thank-you', ['cart' => $cart->id])
                ]
            );
        }

        return to_route('spike.purchase.thank-you', ['cart' => $cart->id]);
    }

    public function addDiscountCode(string $discountCode)
    {
        $this->discount_code_error = '';

        if (empty(trim($discountCode))) {
            $this->discount_code_error = __('spike::translations.discount_code_invalid');
            return;
        }

        $billable = Spike::resolve();
        $promotionCode = $billable->findActivePromotionCode($discountCode);

        if (!$promotionCode) {
            $this->discount_code_error = __('spike::translations.discount_code_invalid');
            return;
        }

        $this->cart()->update(['promotion_code_id' => $promotionCode->id]);
    }

    public function removeDiscountCode()
    {
        $this->cart()->update(['promotion_code_id' => null]);
    }

    protected function cart(): Cart
    {
        if (!isset($this->_cart) && isset($this->cartId)) {
            $billable = Spike::resolve();
            $this->_cart = Cart::whereBillable($billable)
                ->where('id', $this->cartId)
                ->first();
        } elseif (!isset($this->_cart)) {
            $billable = Spike::resolve();
            $this->_cart = Cart::forBillable($billable);
            $this->cartId = $this->_cart->id;
        }

        return $this->_cart;
    }

    public static function closeModalOnClickAway(): bool
    {
        return false;
    }

    public static function closeModalOnEscape(): bool
    {
        return false;
    }

    public static function destroyOnClose(): bool
    {
        return false;
    }

    public static function modalMaxWidth(): string
    {
        return 'xl';
    }
}
