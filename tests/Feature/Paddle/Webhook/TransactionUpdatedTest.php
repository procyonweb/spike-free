<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Paddle\Events\WebhookHandled;
use Opcodes\Spike\Cart;
use Opcodes\Spike\CartItem;
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
    config(['spike.products' => [[
        'id' => 'standard',
        'name' => 'standard',
        'payment_provider_price_id' => 'pri_01'
    ], [
        'id' => 'business',
        'name' => 'business',
        'payment_provider_price_id' => 'pri_02'
    ]]]);
    $this->standardProduct = Spike::findProduct('standard');
    $this->businessProduct = Spike::findProduct('business');
});

it('handles transaction updated event and manages quantity', function () {
    $billable = createBillable();
    $cart = Cart::factory()
        ->forBillable($billable)
        ->has(CartItem::factory([
            'product_id' => $this->standardProduct->id,
            'quantity' => 1
        ]), 'items')
        ->create();

    $webhookEvent = createPaddleTransactionUpdatedWebhookEvent($cart, ['standard' => 2]);
    $listener = new PaddleEventListener();
    $listener->handle($webhookEvent);

    expect($cart->fresh()->items->first()->quantity)->toBe(2);

    $webhookEvent = createPaddleTransactionUpdatedWebhookEvent($cart, ['standard' => 1]);
    $listener->handle($webhookEvent);

    expect($cart->fresh()->items->first()->quantity)->toBe(1);
});

it('handles transaction updated event and removed deleted products from the cart', function () {
    $billable = createBillable();
    $cart = Cart::factory()
        ->forBillable($billable)
        ->has(CartItem::factory(2)->sequence(
            ['product_id' => $this->standardProduct->id, 'quantity' => 1],
            ['product_id' => $this->businessProduct->id, 'quantity' => 1],
        ), 'items')
        ->create();

    $webhookEvent = createPaddleTransactionUpdatedWebhookEvent($cart, ['standard' => 1]);
    $listener = new PaddleEventListener();
    $listener->handle($webhookEvent);

    expect($cart->fresh()->items->count())->toBe(1)
        ->and($cart->fresh()->items->first()->product_id)->toBe($this->standardProduct->id);
});

it('assigns the transaction ID to the cart', function () {
    $billable = createBillable();
    $cart = Cart::factory()
        ->forBillable($billable)
        ->has(CartItem::factory([
            'product_id' => $this->standardProduct->id,
            'quantity' => 1
        ]), 'items')
        ->create();

    $webhookEvent = createPaddleTransactionUpdatedWebhookEvent($cart, ['standard' => 1]);
    $listener = new PaddleEventListener();
    $listener->handle($webhookEvent);

    expect($cart->fresh()->paddle_transaction_id)->toBe('txn_01hn2b49xz6g0zjqv5ysv229fd');
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
    $webhookEvent = createPaddleTransactionUpdatedWebhookEvent($cart, ['standard' => 1]);
    $resolveCalled = false;
    Spike::resolve(function ($request) use (&$resolveCalled) {
        $resolveCalled = true;
    });

    $listener = new PaddleEventListener();
    $listener->handle($webhookEvent);

    $this->assertFalse($resolveCalled, 'Spike::resolve() should not be called');
});

