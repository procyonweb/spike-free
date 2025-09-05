<?php

use Opcodes\Spike\Actions\Products\ProcessCartPayment;
use Opcodes\Spike\Cart;
use Opcodes\Spike\CartItem;
use Opcodes\Spike\Facades\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('ProcessCartPayment processes the payment of the cart', function () {
    $cart = Cart::factory()
        ->for(createBillable(), 'billable')
        ->has(CartItem::factory(), 'items')
        ->create();
    $item = $cart->items->first();

    PaymentGateway::fake();

    (new ProcessCartPayment())->execute($cart);

    PaymentGateway::assertCartPaid($cart);
    PaymentGateway::assertProductPurchased($item->product_id, $item->quantity);
});

test('ProcessCartPayment does nothing if the cart is empty', function () {
    $cart = Cart::factory()
        ->for(createBillable(), 'billable')
        ->create();

    PaymentGateway::fake();

    (new ProcessCartPayment())->execute($cart);

    PaymentGateway::assertCartNotPaid($cart);
    PaymentGateway::assertNothingPurchased();
});
