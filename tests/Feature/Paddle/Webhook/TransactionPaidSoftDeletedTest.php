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
    config(['spike.products' => [[
        'id' => 'standard',
        'name' => 'standard',
        'payment_provider_price_id' => 'pri_01',
        'provides' => [
            CreditAmount::make($this->standardCredits),
        ]
    ]]]);
    $this->standardProduct = Spike::findProduct('standard');
});

it('does not process soft-deleted carts when feature flag is disabled (default)', function () {
    config(['spike.process_soft_deleted_carts' => false]);

    $billable = createBillable();
    $cart = Cart::factory()
        ->forBillable($billable)
        ->has(CartItem::factory([
            'product_id' => $this->standardProduct->id,
            'quantity' => 1
        ]), 'items')
        ->create();

    // Soft-delete the cart
    $cart->delete();
    expect($cart->trashed())->toBeTrue();

    $webhookEvent = createPaddleTransactionPaidWebhookEvent($cart, ['standard' => 1]);
    $listener = new PaddleEventListener();
    $listener->handle($webhookEvent);

    $cart->refresh();

    // Cart should remain unpaid and no credits should be provided
    expect($cart->paid())->toBeFalse()
        ->and($billable->credits()->balance())->toBe(0);
});

it('processes soft-deleted carts when feature flag is enabled', function () {
    config(['spike.process_soft_deleted_carts' => true]);

    $billable = createBillable();
    $cart = Cart::factory()
        ->forBillable($billable)
        ->has(CartItem::factory([
            'product_id' => $this->standardProduct->id,
            'quantity' => 1
        ]), 'items')
        ->create();

    // Soft-delete the cart
    $cart->delete();
    expect($cart->trashed())->toBeTrue();

    $webhookEvent = createPaddleTransactionPaidWebhookEvent($cart, ['standard' => 1]);
    $listener = new PaddleEventListener();
    $listener->handle($webhookEvent);

    $cart->refresh();

    // Cart should be marked as paid and credits should be provided
    expect($cart->paid())->toBeTrue()
        ->and($billable->credits()->balance())->toBe($this->standardCredits);
});

it('processes transaction.updated events for soft-deleted carts when feature flag is enabled', function () {
    config(['spike.process_soft_deleted_carts' => true]);

    $billable = createBillable();
    $cart = Cart::factory()
        ->forBillable($billable)
        ->has(CartItem::factory([
            'product_id' => $this->standardProduct->id,
            'quantity' => 1
        ]), 'items')
        ->create();

    // Soft-delete the cart
    $cart->delete();
    expect($cart->trashed())->toBeTrue();

    $webhookEvent = createPaddleTransactionUpdatedWebhookEvent($cart, ['standard' => 1], 'paid');
    $listener = new PaddleEventListener();
    $listener->handle($webhookEvent);

    $cart->refresh();

    // Cart should be marked as paid and credits should be provided
    expect($cart->paid())->toBeTrue()
        ->and($billable->credits()->balance())->toBe($this->standardCredits);
});

it('does not process transaction.updated events for soft-deleted carts when feature flag is disabled', function () {
    config(['spike.process_soft_deleted_carts' => false]);

    $billable = createBillable();
    $cart = Cart::factory()
        ->forBillable($billable)
        ->has(CartItem::factory([
            'product_id' => $this->standardProduct->id,
            'quantity' => 1
        ]), 'items')
        ->create();

    // Soft-delete the cart
    $cart->delete();
    expect($cart->trashed())->toBeTrue();

    $webhookEvent = createPaddleTransactionUpdatedWebhookEvent($cart, ['standard' => 1], 'paid');
    $listener = new PaddleEventListener();
    $listener->handle($webhookEvent);

    $cart->refresh();

    // Cart should remain unpaid and no credits should be provided
    expect($cart->paid())->toBeFalse()
        ->and($billable->credits()->balance())->toBe(0);
});

it('still processes active carts normally when feature flag is enabled', function () {
    config(['spike.process_soft_deleted_carts' => true]);

    $billable = createBillable();
    $cart = Cart::factory()
        ->forBillable($billable)
        ->has(CartItem::factory([
            'product_id' => $this->standardProduct->id,
            'quantity' => 1
        ]), 'items')
        ->create();

    // Cart is not soft-deleted
    expect($cart->trashed())->toBeFalse();

    $webhookEvent = createPaddleTransactionPaidWebhookEvent($cart, ['standard' => 1]);
    $listener = new PaddleEventListener();
    $listener->handle($webhookEvent);

    $cart->refresh();

    // Cart should be marked as paid and credits should be provided
    expect($cart->paid())->toBeTrue()
        ->and($billable->credits()->balance())->toBe($this->standardCredits);
});


