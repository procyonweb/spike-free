<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Paddle\Events\WebhookHandled;
use Opcodes\Spike\Cart;
use Opcodes\Spike\Events\InvoicePaid;
use Opcodes\Spike\Facades\PaymentGateway;
use Opcodes\Spike\Paddle\Listeners\PaddleEventListener;

uses(RefreshDatabase::class);

beforeAll(function () {
    \Opcodes\Spike\SpikeServiceProvider::$_cached_payment_provider = \Opcodes\Spike\PaymentProvider::Paddle;
});
afterAll(function () {
    \Opcodes\Spike\SpikeServiceProvider::$_cached_payment_provider = null;
});

it('dispatches InvoiceCreated event when transaction.completed webhook is received', function () {
    $billable = createBillable();
    Event::fake();

    $event = createPaddleTransactionWebhookEvent('transaction.completed', [
        'id' => 'txn_123',
        'invoice_number' => 'INV-123',
        'status' => 'completed',
        'billed_at' => $this->billed_at = now()->subHour()->toIso8601String(),
        'completed_at' => now()->toIso8601String(),
        'customer_id' => $billable->customer->paddle_id,
        'currency_code' => 'USD',
        'details' => [
            'totals' => [
                'total' => '1999',
                'grand_total' => '1999',
            ],
        ],
        'items' => [],
    ]);

    $listener = new PaddleEventListener();
    $listener->handle($event);

    Event::assertDispatched(InvoicePaid::class, function ($event) use ($billable) {
        return $event->billable->id === $billable->id
            && $event->invoice->id === 'txn_123'
            && $event->invoice->number === 'INV-123'
            && $event->invoice->status === 'completed'
            && $event->invoice->date->timestamp === \Illuminate\Support\Carbon::parse($this->billed_at)->timestamp
            && $event->invoice->total === '$19.99';
    });
});
