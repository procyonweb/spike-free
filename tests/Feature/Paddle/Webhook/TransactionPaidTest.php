<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Paddle\Events\WebhookHandled;
use Opcodes\Spike\Cart;
use Opcodes\Spike\CartItem;
use Opcodes\Spike\CreditAmount;
use Opcodes\Spike\Facades\Spike;
use Opcodes\Spike\Paddle\Listeners\PaddleEventListener;

uses(RefreshDatabase::class);

beforeAll(function () {
    \Opcodes\Spike\SpikeServiceProvider::$_cached_payment_provider = \Opcodes\Spike\PaymentProvider::Paddle;
});
afterAll(function () {
    \Opcodes\Spike\SpikeServiceProvider::$_cached_payment_provider = null;
});

beforeEach(function () {
    $this->standardCredits = 100;
    $this->businessCredits = 200;
    config(['spike.products' => [[
        'id' => 'standard',
        'name' => 'standard',
        'payment_provider_price_id' => 'pri_01',
        'provides' => [
            CreditAmount::make($this->standardCredits),
        ]
    ], [
        'id' => 'business',
        'name' => 'business',
        'payment_provider_price_id' => 'pri_02',
        'provides' => [
            CreditAmount::make($this->businessCredits),
        ]
    ]]]);
    $this->standardProduct = Spike::findProduct('standard');
    $this->businessProduct = Spike::findProduct('business');
});

it('handles transaction.paid event', function () {
    $billable = createBillable();
    $cart = Cart::factory()
        ->forBillable($billable)
        ->has(CartItem::factory([
            'product_id' => $this->standardProduct->id,
            'quantity' => 1
        ]), 'items')
        ->create();

    $webhookEvent = createPaddleTransactionPaidWebhookEvent($cart, ['standard' => 1]);
    $listener = new PaddleEventListener();
    $listener->handle($webhookEvent);

    $cart->refresh();

    expect($cart->paid())->toBeTrue()
        ->and($billable->credits()->balance())->toBe($this->standardCredits);
});

it('does not call Spike::resolve()', function () {
    $billable = createBillable();
    $cart = Cart::factory()
        ->forBillable($billable)
        ->has(CartItem::factory([
            'product_id' => $this->standardProduct->id,
            'quantity' => 1
        ]), 'items')
        ->create();
    $webhookEvent = createPaddleTransactionPaidWebhookEvent($cart, ['standard' => 1]);
    $resolveCalled = false;
    Spike::resolve(function ($request) use (&$resolveCalled) {
        $resolveCalled = true;
    });

    $listener = new PaddleEventListener();
    $listener->handle($webhookEvent);

    $this->assertFalse($resolveCalled, 'Spike::resolve() should not be called');
});

