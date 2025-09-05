<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Opcodes\Spike\Cart;
use Opcodes\Spike\CartItem;
use Opcodes\Spike\CreditAmount;
use Opcodes\Spike\Events\ProductPurchased;
use function Spatie\PestPluginTestTime\testTime;

uses(RefreshDatabase::class);

it('marks cart as paid after invoice.payment_succeeded webhook', function () {
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

    $billable = createBillable();
    $billable->stripe_id = 'cus_test';
    $billable->save();
    $cart = Cart::factory()->forBillable($billable)->create();
    $item = CartItem::factory()->for($cart)->create([
        'product_id' => $product1['id'],
        'quantity' => 2,
    ]);
    $item2 = CartItem::factory()->for($cart)->create([
        'product_id' => $product2['id'],
        'quantity' => 2,
    ]);

    $this->assertFalse($cart->paid());

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
    $this->assertTrue($cart->paid());
    Event::assertDispatchedTimes(ProductPurchased::class, 2);

    // repeating the webhook should not change anything.
    $cartPaidAt = $cart->paid_at->copy();
    testTime()->addHour();
    Event::fake();
    $this->postJson('/stripe/webhook', $stripeEvent)
        ->assertSuccessful();

    $this->assertEquals($cartPaidAt, $cart->fresh()->paid_at);
    Event::assertNotDispatched(ProductPurchased::class);
});
