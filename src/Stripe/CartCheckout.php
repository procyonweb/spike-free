<?php

namespace Opcodes\Spike\Stripe;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;
use Laravel\Cashier\Checkout;
use Opcodes\Spike\Cart;
use Opcodes\Spike\CartItem;
use Opcodes\Spike\Facades\Spike;

class CartCheckout
{
    public function __construct(
        protected Cart $cart
    )
    {
    }

    public function resetCheckout(): void
    {
        $this->cart->update([
            'stripe_checkout_session_id' => null,
        ]);
    }

    /**
     * @param array $options Additional options to pass to the Stripe checkout session
     * @param string|null $successUrl URL to redirect to after successful payment
     * @param string|null $cancelUrl URL to redirect to after payment is canceled
     * @return RedirectResponse|Redirector
     */
    public function redirectToStripeCheckout(array $options = [], ?string $successUrl = null, ?string $cancelUrl = null)
    {
        return $this->newStripeCheckout($options, $successUrl, $cancelUrl)->redirect();
    }

    /**
     * @param array $options Additional options to pass to the Stripe checkout session
     * @param string|null $successUrl URL to redirect to after successful payment
     * @param string|null $cancelUrl URL to redirect to after payment is canceled
     * @return Checkout
     */
    public function newStripeCheckout($options = [], ?string $successUrl = null, ?string $cancelUrl = null): Checkout
    {
        $successUrl = $successUrl ?? $options['success_url'] ?? null;
        $cancelUrl = $cancelUrl ?? $options['cancel_url'] ?? null;

        if (config('spike.path')) {
            $successUrl = $successUrl ?? route('spike.purchase.validate-cart', ['cart' => $this->cart->id]);
            $cancelUrl = $cancelUrl ?? route('spike.purchase');
        }

        $options['success_url'] = $successUrl;
        $options['cancel_url'] = $cancelUrl;

        if (empty($options['success_url'])) {
            throw new \InvalidArgumentException('Success URL for Stripe checkout is required when billing portal is disabled.');
        }

        if (empty($options['cancel_url'])) {
            throw new \InvalidArgumentException('Cancel URL for Stripe checkout is required when billing portal is disabled.');
        }

        $currency = $this->cart->validateAndDetermineCurrency();

        if (isset($currency)) {
            // We're going into the realm of custom currencies, and there's a 
            // special way we need to handle that with Stripe Checkout.
            $items = $this->prepareItemsWithCustomCurrency($currency);
        } else {
            $items = $this->prepareItems();
        }

        $billable = $this->cart->billable;

        if (Spike::stripeAllowDiscounts()) {
            $billable = $billable->allowPromotionCodes();
        }

        $generateInvoices = config(
            'spike.stripe.checkout.generate_invoices_for_products',
            config(
                'spike.stripe_checkout.generate_invoices',
                config('spike.stripe_checkout.generate_invoices_for_products')
            )
        );

        if ($generateInvoices) {
            $options['invoice_creation'] = ['enabled' => true];
        }

        if ($locale = config('spike.stripe.checkout.default_locale')) {
            $options['locale'] = $locale;
        }

        $checkout = $billable->checkout($items, $options);

        $this->cart->update([
            'stripe_checkout_session_id' => $checkout->asStripeCheckoutSession()->id
        ]);

        return $checkout;
    }

    public function toStripeCheckoutSession(): ?\Stripe\Checkout\Session
    {
        if (!$this->cart->stripe_checkout_session_id) return null;

        \Stripe\Stripe::setApiKey(config('cashier.secret'));

        return \Stripe\Checkout\Session::retrieve($this->cart->stripe_checkout_session_id);
    }

    protected function prepareItems(): array
    {
        return $this->cart->items->map(function (CartItem $item) {
            $product = $item->product();

            return array_filter([
                'price' => $product->payment_provider_price_id,
                'quantity' => $item->quantity,
            ]);
        })->toArray();
    }

    protected function prepareItemsWithCustomCurrency(string $currency): array
    {
        $stripeApi = $this->cart->billable->stripe();

        return $this->cart->items->map(function (CartItem $item) use ($stripeApi) {
            $product = $item->product();

            // Unfortunately we cannot fetch all prices at once, hence we're doing it
            // one by one for every item in the cart. Every item is a different price ID.
            $stripePrice = $stripeApi->prices->retrieve($product->payment_provider_price_id);

            return array_filter([
                'quantity' => $item->quantity,
                'price_data' => array_filter([
                    'product' => $stripePrice->product,
                    'currency' => $product->currency ?? config('cashier.currency'),
                    'unit_amount' => $product->price_in_cents,
                ]),
            ]);
        })->toArray();
    }
}
