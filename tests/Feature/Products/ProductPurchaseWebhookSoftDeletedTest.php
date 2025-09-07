<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Opcodes\Spike\Cart;
use Opcodes\Spike\CartItem;
use Opcodes\Spike\CreditAmount;
use Opcodes\Spike\Events\ProductPurchased;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['spike.products' => [
        $this->product1 = [
            'id' => 'standard',
            'name' => 'standard',
            'provides' => [
                CreditAmount::make(500),
                CreditAmount::make(100, 'sms'),
            ]
        ]
    ]]);
});

it('does not process soft-deleted carts when feature flag is disabled (default)', function () {
    config(['spike.process_soft_deleted_carts' => false]);

    $billable = createBillable();
    $billable->stripe_id = 'cus_test';
    $billable->save();
    $cart = Cart::factory()->forBillable($billable)->create();
    $item = CartItem::factory()->for($cart)->create([
        'product_id' => $this->product1['id'],
        'quantity' => 1,
    ]);

    // Soft-delete the cart
    $cart->delete();
    expect($cart->trashed())->toBeTrue();

    $stripeEvent = [
        'id' => 'evt_test',
        'type' => 'invoice.payment_succeeded',
        'data' => [
            'object' => [
                'id' => 'in_test',
                'customer' => $billable->stripe_id,
                'status' => 'paid',
                'total' => 1000,
                'number' => '123456789',
                'currency' => 'usd',
                'status_transitions' => [
                    'paid_at' => now()->toIso8601String(),
                ],
                'metadata' => [
                    'spike_cart_id' => $cart->id,
                ],
            ]
        ]
    ];

    Event::fake();

    $this->postJson('/stripe/webhook', $stripeEvent)
        ->assertSuccessful();

    $cart->refresh();
    
    // Cart should remain unpaid and no events should be dispatched
    expect($cart->paid())->toBeFalse()
        ->and($billable->credits()->balance())->toBe(0);
        
    Event::assertNotDispatched(ProductPurchased::class);
});

it('processes soft-deleted carts when feature flag is enabled', function () {
    config(['spike.process_soft_deleted_carts' => true]);

    $billable = createBillable();
    $billable->stripe_id = 'cus_test';
    $billable->save();
    $cart = Cart::factory()->forBillable($billable)->create();
    $item = CartItem::factory()->for($cart)->create([
        'product_id' => $this->product1['id'],
        'quantity' => 1,
    ]);

    // Soft-delete the cart
    $cart->delete();
    expect($cart->trashed())->toBeTrue();

    $stripeEvent = [
        'id' => 'evt_test',
        'type' => 'invoice.payment_succeeded',
        'data' => [
            'object' => [
                'id' => 'in_test',
                'customer' => $billable->stripe_id,
                'status' => 'paid',
                'total' => 1000,
                'number' => '123456789',
                'currency' => 'usd',
                'status_transitions' => [
                    'paid_at' => now()->toIso8601String(),
                ],
                'metadata' => [
                    'spike_cart_id' => $cart->id,
                ],
            ]
        ]
    ];

    Event::fake();

    $this->postJson('/stripe/webhook', $stripeEvent)
        ->assertSuccessful();

    $cart->refresh();
    
    // Cart should be marked as paid and credits should be provided
    expect($cart->paid())->toBeTrue()
        ->and($billable->credits()->balance())->toBe(500)
        ->and($billable->credits('sms')->balance())->toBe(100);
        
    Event::assertDispatched(ProductPurchased::class);
});

it('still processes active carts normally when feature flag is enabled', function () {
    config(['spike.process_soft_deleted_carts' => true]);

    $billable = createBillable();
    $billable->stripe_id = 'cus_test';
    $billable->save();
    $cart = Cart::factory()->forBillable($billable)->create();
    $item = CartItem::factory()->for($cart)->create([
        'product_id' => $this->product1['id'],
        'quantity' => 1,
    ]);

    // Cart is not soft-deleted
    expect($cart->trashed())->toBeFalse();

    $stripeEvent = [
        'id' => 'evt_test',
        'type' => 'invoice.payment_succeeded',
        'data' => [
            'object' => [
                'id' => 'in_test',
                'customer' => $billable->stripe_id,
                'status' => 'paid',
                'total' => 1000,
                'number' => '123456789',
                'currency' => 'usd',
                'status_transitions' => [
                    'paid_at' => now()->toIso8601String(),
                ],
                'metadata' => [
                    'spike_cart_id' => $cart->id,
                ],
            ]
        ]
    ];

    Event::fake();

    $this->postJson('/stripe/webhook', $stripeEvent)
        ->assertSuccessful();

    $cart->refresh();
    
    // Cart should be marked as paid and credits should be provided
    expect($cart->paid())->toBeTrue()
        ->and($billable->credits()->balance())->toBe(500)
        ->and($billable->credits('sms')->balance())->toBe(100);
        
    Event::assertDispatched(ProductPurchased::class);
});

it('processes multiple items in soft-deleted cart when feature flag is enabled', function () {
    config(['spike.process_soft_deleted_carts' => true]);
    
    config(['spike.products' => [
        $this->product1,
        $product2 = [
            'id' => 'pro',
            'name' => 'pro',
            'provides' => [
                CreditAmount::make(1000),
                CreditAmount::make(200, 'sms'),
            ]
        ]
    ]]);

    $billable = createBillable();
    $billable->stripe_id = 'cus_test';
    $billable->save();
    $cart = Cart::factory()->forBillable($billable)->create();
    
    CartItem::factory()->for($cart)->create([
        'product_id' => $this->product1['id'],
        'quantity' => 2,
    ]);
    CartItem::factory()->for($cart)->create([
        'product_id' => $product2['id'],
        'quantity' => 1,
    ]);

    // Soft-delete the cart
    $cart->delete();
    expect($cart->trashed())->toBeTrue();

    $stripeEvent = [
        'id' => 'evt_test',
        'type' => 'invoice.payment_succeeded',
        'data' => [
            'object' => [
                'id' => 'in_test',
                'customer' => $billable->stripe_id,
                'status' => 'paid',
                'total' => 2000,
                'number' => '123456789',
                'currency' => 'usd',
                'status_transitions' => [
                    'paid_at' => now()->toIso8601String(),
                ],
                'metadata' => [
                    'spike_cart_id' => $cart->id,
                ],
            ]
        ]
    ];

    Event::fake();

    $this->postJson('/stripe/webhook', $stripeEvent)
        ->assertSuccessful();

    $cart->refresh();
    
    // Cart should be marked as paid and all credits should be provided
    expect($cart->paid())->toBeTrue()
        // 2 * 500 (standard) + 1 * 1000 (pro) = 2000 credits
        ->and($billable->credits()->balance())->toBe(2000)  
        // 2 * 100 (standard) + 1 * 200 (pro) = 400 sms credits
        ->and($billable->credits('sms')->balance())->toBe(400);
        
    Event::assertDispatchedTimes(ProductPurchased::class, 2);
});

it('does not double-process already paid soft-deleted carts when feature flag is enabled', function () {
    config(['spike.process_soft_deleted_carts' => true]);

    $billable = createBillable();
    $billable->stripe_id = 'cus_test';
    $billable->save();
    $cart = Cart::factory()->forBillable($billable)->create();
    $item = CartItem::factory()->for($cart)->create([
        'product_id' => $this->product1['id'],
        'quantity' => 1,
    ]);

    // Mark cart as paid first
    $cart->markAsSuccessfullyPaid();
    
    // Then soft-delete it
    $cart->delete();
    expect($cart->trashed())->toBeTrue();
    expect($cart->paid())->toBeTrue();

    // Reset credits to test double processing
    $initialBalance = $billable->credits()->balance();

    $stripeEvent = [
        'id' => 'evt_test',
        'type' => 'invoice.payment_succeeded',
        'data' => [
            'object' => [
                'id' => 'in_test',
                'customer' => $billable->stripe_id,
                'status' => 'paid',
                'total' => 1000,
                'number' => '123456789',
                'currency' => 'usd',
                'status_transitions' => [
                    'paid_at' => now()->toIso8601String(),
                ],
                'metadata' => [
                    'spike_cart_id' => $cart->id,
                ],
            ]
        ]
    ];

    Event::fake();

    $this->postJson('/stripe/webhook', $stripeEvent)
        ->assertSuccessful();

    $cart->refresh();
    
    // Credits should not be double-processed
    expect($billable->credits()->balance())->toBe($initialBalance);
    
    Event::assertNotDispatched(ProductPurchased::class);
});