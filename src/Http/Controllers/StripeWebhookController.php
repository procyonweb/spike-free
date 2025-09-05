<?php

namespace Opcodes\Spike\Http\Controllers;

use Illuminate\Support\Carbon;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Http\Controllers\WebhookController;
use Opcodes\Spike\Cart;
use Opcodes\Spike\Events\InvoicePaid;
use Opcodes\Spike\Facades\PaymentGateway;
use Opcodes\Spike\SpikeInvoice;

class StripeWebhookController extends WebhookController
{
    protected function handleInvoicePaymentSucceeded(array $payload)
    {
        if ($user = PaymentGateway::findBillable($payload['data']['object']['customer'])) {
            // In case this payment is for a Spike cart:
            if ($cartId = ($payload['data']['object']['metadata']['spike_cart_id'] ?? null)) {
                $cart = Cart::forBillable($user)->find($cartId);
                $cart?->markAsSuccessfullyPaid();
            }

            $invoice = $payload['data']['object'];

            $spikeInvoice = new SpikeInvoice(
                id: $invoice['id'],
                number: $invoice['number'],
                date: Carbon::parse($invoice['status_transitions']['paid_at']),
                status: $invoice['status'],
                total: Cashier::formatAmount($invoice['total'], $invoice['currency']),
            );

            event(new InvoicePaid($user, $spikeInvoice));
        }

        return $this->successMethod();
    }
}
