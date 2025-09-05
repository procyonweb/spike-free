<?php

namespace Opcodes\Spike\Http\Controllers;

use Opcodes\Spike\Actions\Products\StripeCheckoutRedirectWithLock;
use Opcodes\Spike\Cart;
use Opcodes\Spike\Facades\Spike;
use Illuminate\Http\Request;

class PurchasesController
{
    public function index(Request $request)
    {
        if (Spike::products()->isEmpty()) {
            return redirect(route('spike.usage'));
        }

        $cart = Cart::forBillable(Spike::resolve());
        $cart->syncWithStripeCheckoutSession();

        if ($cart->paid()) {
            return to_route('spike.purchase.thank-you', ['cart' => $cart->id]);
        }

        $this->setProductsFromQuery($request, $cart);

        if ($request->boolean('pay') && $cart->notEmpty() && Spike::stripeCheckoutEnabled()) {
            return app(StripeCheckoutRedirectWithLock::class)->handle($cart);
        }
        // TODO: add handling for Paddle checkout??

        return view('spike::purchase');
    }

    public function validateCart(Cart $cart)
    {
        if ($cart->billable->isNot(Spike::resolve())) {
            return to_route('spike.purchase');
        }

        $cart->syncWithStripeCheckoutSession();

        if ($cart->paid()) {
            return to_route('spike.purchase.thank-you', ['cart' => $cart->id]);
        }

        return view('spike::validate-cart', [
            'cart' => $cart,
        ]);
    }

    public function success(Cart $cart)
    {
        if (!$cart->paid() || $cart->billable->isNot(Spike::resolve())) {
            return to_route('spike.purchase');
        }

        // figure out how to redirect
        list('url' => $url, 'delay' => $delay) = Spike::getRedirectAfterProductPurchaseTo();

        if (is_callable($url)) {
            $url = $url($cart);
        }

        if (!empty($url) && $delay <= 0) {
            return redirect($url);
        }

        return view('spike::thank-you', [
            'cart' => $cart,
            'redirect_to' => $url,
            'redirect_delay' => $delay,
        ]);
    }

    protected function setProductsFromQuery(Request $request, Cart $cart)
    {
        $products = $request->query('products', []);

        if (!empty($products)) {
            $cart->items()->delete();
        }

        foreach ($products as $productId => $amount) {
            $amount = intval($amount);
            $amount = max(0, $amount);

            if ($amount && Spike::findProduct($productId)) {
                $cart->addProduct($productId, $amount);
            }
        }
    }
}
