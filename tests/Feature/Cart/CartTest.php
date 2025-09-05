<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Opcodes\Spike\Cart;
use Opcodes\Spike\CartItem;
use Opcodes\Spike\CreditAmount;
use Opcodes\Spike\Tests\Fixtures\SampleProvidable;

uses(RefreshDatabase::class);

it('it can calculate the total provides, combined', function () {
    config(['spike.products' => [
        $product1 = [
            'id' => 'standard',
            'name' => 'standard',
            'provides' => [
                CreditAmount::make(500),
                CreditAmount::make(100, 'sms'),
            ]
        ],
        $product2 = [
            'id' => 'pro',
            'name' => 'pro',
            'provides' => [
                CreditAmount::make(1000),
                CreditAmount::make(200, 'sms'),
            ]
        ]
    ]]);

    $cart = Cart::factory()->forBillable(createBillable())->create();
    $item = CartItem::factory()->for($cart)->create([
        'product_id' => $product1['id'],
        'quantity' => 2,
    ]);
    $item2 = CartItem::factory()->for($cart)->create([
        'product_id' => $product2['id'],
        'quantity' => 2,
    ]);

    expect($cart->totalProvides())
        ->toBeInstanceOf(Collection::class)
        ->and($cart->totalProvides()->count())->toBe(2)
        ->and($cart->totalProvides()->first()->getAmount())->toBe(1000 + 2000)
        ->and($cart->totalProvides()->first()->getType()->type)->toBe('credits')
        ->and($cart->totalProvides()->last()->getAmount())->toBe(200 + 400)
        ->and($cart->totalProvides()->last()->getType()->type)->toBe('sms');
});

it('keeps only unique providables that are not countable', function () {
    $providable1 = new SampleProvidable();
    $providable2 = new class extends SampleProvidable {};

    config(['spike.products' => [
        $product = [
            'id' => 'standard',
            'name' => 'standard',
            'provides' => [
                $providable1,
                $providable2,
            ]
        ]
    ]]);

    $cart = Cart::factory()->forBillable(createBillable())->create();
    CartItem::factory()->for($cart)->create([
        'product_id' => $product['id'],
        'quantity' => 2,
    ]);
    CartItem::factory()->for($cart)->create([
        'product_id' => $product['id'],
        'quantity' => 1,
    ]);

    expect($cart->totalProvides())
        ->toBeInstanceOf(Collection::class)
        ->and($cart->totalProvides()->count())->toBe(2)
        ->and($cart->totalProvides()->first())->toBe($providable1)
        ->and($cart->totalProvides()->last())->toBe($providable2);
});
