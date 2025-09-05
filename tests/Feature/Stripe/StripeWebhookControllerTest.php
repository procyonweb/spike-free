<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Cashier\Cashier;
use Opcodes\Spike\Events\InvoicePaid;
use Opcodes\Spike\Facades\PaymentGateway;
use Opcodes\Spike\Facades\Spike;

uses(RefreshDatabase::class);

beforeEach(function () {
    PaymentGateway::fake();
    $this->billable = createBillable();
    $this->billable->stripe_id = $this->customerId = 'cus_123';
    $this->billable->save();
});

it('does not call Spike::resolve()', function () {
    config(['spike.stripe.checkout.enabled' => true]);
    $resolveCalled = false;

    Spike::resolve(function ($request) use (&$resolveCalled) {
        $resolveCalled = true;
    });

    $event = [
        'type' => 'invoice.payment_succeeded',
        'data' => [
            'object' => [
                'id' => 'in_123',
                'customer' => $this->customerId,
                'currency' => 'usd',
                'number' => 'INV-123',
                'status' => 'paid',
                'total' => 1000,
                'status_transitions' => [
                    'paid_at' => now()->toIso8601String(),
                ],
            ],
        ],
    ];

    $this->postJson('/stripe/webhook', $event)
        ->assertSuccessful();

    $this->assertFalse($resolveCalled, 'Spike::resolve() should not be called');
});

it('dispatches InvoiceCreated event when invoice.paid webhook is received', function () {
    Event::fake();

    $event = [
        'type' => 'invoice.payment_succeeded',
        'data' => [
            'object' => [
                'id' => 'in_123',
                'customer' => $this->customerId,
                'currency' => 'usd',
                'number' => 'INV-123',
                'status' => 'paid',
                'total' => 1000,
                'status_transitions' => [
                    'paid_at' => now()->toIso8601String(),
                ],
            ],
        ],
    ];

    $this->postJson('/stripe/webhook', $event)
        ->assertSuccessful();

    Event::assertDispatched(InvoicePaid::class, function ($event) {
        return $event->billable->id === $this->billable->id
            && $event->invoice->id === 'in_123'
            && $event->invoice->number === 'INV-123'
            && $event->invoice->status === 'paid'
            && $event->invoice->total === Cashier::formatAmount(1000, 'usd');
    });
});
