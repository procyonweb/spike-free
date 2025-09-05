<?php

namespace Opcodes\Spike\Paddle\Listeners;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Laravel\Paddle\Cashier;
use Laravel\Paddle\Events\WebhookHandled;
use Opcodes\Spike\Actions\Products\ProvideCartProvidables;
use Opcodes\Spike\Actions\Subscriptions\ProvideSubscriptionPlanMonthlyProvides;
use Opcodes\Spike\Cart;
use Opcodes\Spike\CartItem;
use Opcodes\Spike\Events\InvoicePaid;
use Opcodes\Spike\Events\ProductPurchased;
use Opcodes\Spike\Facades\Spike;
use Opcodes\Spike\SpikeInvoice;
use Opcodes\Spike\Paddle\Subscription;
use Opcodes\Spike\Paddle\Transaction;

class PaddleEventListener
{
    /**
     * Handle received Paddle webhooks.
     */
    public function handle(WebhookHandled $event): void
    {
        if (config('app.debug')) {
            Log::debug('Received payload from Paddle:', $event->payload);
        }

        list(
            'data' => $data,
            'event_id' => $eventId,
            'event_type' => $eventType,
            'occurred_at' => $occurredAt,
            'notification_id' => $notificationId,
        ) = $event->payload;

        switch ($eventType) {
            case 'transaction.updated':
            case 'transaction.paid':
                $this->handleTransactionUpdated($data);
                break;

            case 'transaction.completed':
                $this->handleTransactionCompleted($data);
                break;

            case 'subscription.created':
            case 'subscription.updated':
            case 'subscription.activated':
                $this->handleSubscriptionUpdated($data);
                break;

            default:
                // do nothing for the other event types.
        }
    }

    private function handleTransactionUpdated(array $data)
    {
        $customer = Cashier::findBillable($data['customer_id']);

        if (! $customer) {
            return;
        }

        $cartId = $data['custom_data']['spike_cart_id'] ?? 0;

        if ($cartId && ($cart = Cart::find($cartId))) {
            $this->processCartFromTransactionUpdated($cart, $data);
        }
    }

    private function handleTransactionCompleted(array $data)
    {
        $customer = Cashier::findBillable($data['customer_id']);

        if (! $customer) {
            return;
        }

        $this->invoicePaid($customer, $data);
    }

    private function processCartFromTransactionUpdated(Cart $cart, array $data): void
    {
        if (isset($data['id']) && is_null($cart->paddle_transaction_id)) {
            $cart->paddle_transaction_id = $data['id'];
            $cart->save();
        }

        $this->updateCartItemQuantities($cart, $data['items']);

        if (in_array($data['status'], [Transaction::STATUS_PAID, Transaction::STATUS_COMPLETED]) && ! $cart->paid()) {
            $cart->paid_at = now();
            $cart->save();

            app(ProvideCartProvidables::class)->handle($cart);

            $cart->items->each(function (CartItem $item) use ($cart) {
                event(new ProductPurchased(
                    $cart->billable,
                    $item->product(),
                    $item->quantity
                ));
            });
        }
    }

    private function invoicePaid(mixed $billable, array $data): void
    {
        $spikeInvoice = new SpikeInvoice(
            id: $data['id'],
            number: $data['invoice_number'] ?? $data['id'],
            date: Carbon::parse($data['billed_at']),
            status: $data['status'],
            total: Cashier::formatAmount($data['details']['totals']['grand_total'], $data['currency_code']),
        );

        event(new InvoicePaid($billable, $spikeInvoice));
    }

    private function updateCartItemQuantities(Cart $cart, array $webhookItems): void
    {
        // let's check every item in the cart and compare it to the items in the transaction.
        // maybe some items have changed quantity, or have been removed. We need to update our cart accordingly.

        $webhookItems = collect($webhookItems);

        $cart->items->each(function (CartItem $item) use ($webhookItems) {
            $webhookItem = $webhookItems->firstWhere('price_id', $item->product()->payment_provider_price_id);

            if (! $webhookItem) {
                // this item has been removed from the transaction.
                $item->delete();
            } elseif ($webhookItem['quantity'] !== $item->quantity) {
                // this item has changed quantity.
                $item->update(['quantity' => $webhookItem['quantity']]);
            }
        });
    }

    private function handleSubscriptionUpdated(array $data)
    {
        $subscription = Subscription::where('paddle_id', $data['id'])->first();

        if (! $subscription) {
            Log::warning('[Paddle\PaddleEventListener] Got a webhook for a subscription that does not yet exist in database. Might be a race-condition with Cashier.', [
                'webhook' => $data,
            ]);
            return;
        }

        // Don't provide credits if the subscription is not active
        if (! $subscription->active()) {
            Log::info('[Paddle\PaddleEventListener] Subscription is not active, skipping monthly provides.', [
                'subscription_id' => $subscription->id,
                'paddle_sub_id' => $subscription->paddle_id,
                'status' => $data['status'],
            ]);
            return;
        }

        $billable = $subscription->billable;

        $plan = Spike::findSubscriptionPlan(
            $subscription->items->first()->price_id,
            $billable
        );

        app(ProvideSubscriptionPlanMonthlyProvides::class)->handle(
            $plan,
            $billable,
            $subscription->items->first()
        );
    }
}
