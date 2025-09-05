<?php

namespace Opcodes\Spike\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Paddle\Cashier;
use Opcodes\Spike\Exceptions\MissingPaymentProviderException;
use Opcodes\Spike\Facades\Spike;
use Opcodes\Spike\Paddle\Transaction;
use Opcodes\Spike\PaymentProvider;

class BillingController
{
    public function index()
    {
        return view('spike::billing');
    }

    public function downloadInvoice(Request $request, $id)
    {
        return match (Spike::paymentProvider()) {
            PaymentProvider::Stripe => $this->downloadStripeInvoice($request, $id),
            PaymentProvider::Paddle => $this->downloadPaddleInvoice($request, $id),
            default => throw new MissingPaymentProviderException(),
        };
    }

    protected function downloadStripeInvoice(Request $request, $id)
    {
        $billable = Spike::resolve();

        return $billable->downloadInvoice(
            $id,
            config(
                'spike.stripe.invoice_details',
                config('spike.invoice_details', [])
            ),
            $request->query('filename')
        );
    }

    protected function downloadPaddleInvoice(Request $request, $id)
    {
        $billable = Spike::resolve();

        /** @var Transaction $transaction */
        $transaction = $billable->transactions()->findOrFail($id);

        $paddleInvoiceUrl = Cashier::api('GET', "transactions/{$transaction->paddle_id}/invoice")['data']['url'] ?? null;

        abort_if(is_null($paddleInvoiceUrl), 404);

        return response(file_get_contents($paddleInvoiceUrl))
            ->header('Content-Disposition', 'attachment; filename="' . $request->query('filename') . '.pdf"');
    }
}
