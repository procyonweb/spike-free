<?php

namespace Opcodes\Spike\Http\Controllers;

use Illuminate\Http\Request;
use Opcodes\Spike\Cart;
use Opcodes\Spike\Facades\Spike;

class MollieController
{
    public function checkoutSuccess(Cart $cart)
    {
        if (!$cart->isProcessed()) {
            // Payment is being processed, show a waiting page
            return view('spike::mollie.checkout.processing', compact('cart'));
        }

        if (!$cart->paid()) {
            return to_route('spike.purchase')->with('error', 'Payment was not completed.');
        }

        // Redirect to the thank you page (reuse the same logic as other providers)
        return to_route('spike.purchase.thank-you', ['cart' => $cart->id]);
    }

    public function webhook(Request $request)
    {
        // Mollie webhook will be handled by Cashier Mollie's webhook handler
        // The MollieWebhookListener will listen to the WebhookHandled event
        return response('Webhook received', 200);
    }
}
