<?php

use Opcodes\Spike\Actions\Products\ProcessCart;
use Opcodes\Spike\Cart;
use Opcodes\Spike\CartItem;
use Opcodes\Spike\Contracts\ProcessCartPaymentContract;
use Opcodes\Spike\CreditAmount;
use Opcodes\Spike\Events\ProductPurchased;
use Opcodes\Spike\Facades\Credits;
use Opcodes\Spike\Facades\PaymentGateway;
use Opcodes\Spike\Facades\Spike;
use Opcodes\Spike\PaymentProvider;
use Opcodes\Spike\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->productOne = new Product(
        id: 'standard',
        name: 'standard',
        payment_provider_price_id: 'stripe_id',
        price_in_cents: 1000,
        provides: [CreditAmount::make(500)]
    );
    $this->productTwo = new Product(
        id: 'second',
        name: 'second',
        payment_provider_price_id: 'second_stripe_id',
        price_in_cents: 2000,
        provides: [CreditAmount::make(1000)]
    );
    Spike::resolveProductsUsing(fn () => [$this->productOne, $this->productTwo]);
});

test('ProcessCart calls the cart payment processing action', function () {
    $cart = Cart::factory()->for(createBillable(), 'billable')
        ->has(CartItem::factory(1, ['product_id' => $this->productOne->id]), 'items')
        ->create();

    $this->mock(
        ProcessCartPaymentContract::class,
        fn (MockInterface $mock) => $mock
            ->shouldReceive('execute')->with($cart)
            ->atLeast()->once()->andReturn(true)
    );

    app(ProcessCart::class)->execute($cart);
});

test('ProcessCart does not call payment processing if the cart total is zero', function () {
    $cart = Cart::factory()->for(createBillable(), 'billable')
        ->has(CartItem::factory(1, ['product_id' => $this->productOne->id]), 'items')
        ->create();
    $this->productOne->price_in_cents = 0;
    expect($cart->totalPriceInCents())->toBe(0);

    $this->mock(
        ProcessCartPaymentContract::class,
        fn (MockInterface $mock) => $mock->shouldNotReceive('execute')
    );

    app(ProcessCart::class)->execute($cart);
});

test('ProcessCart adds credits after purchase is complete', function () {
    $billable = createBillable();
    $cart = Cart::factory()
        ->for($billable, 'billable')
        ->has(CartItem::factory(1, ['product_id' => $this->productOne->id]), 'items')
        ->has(CartItem::factory(1, ['product_id' => $this->productTwo->id]), 'items')
        ->create();
    expect($billable->credits()->balance())->toBe(0);
    PaymentGateway::fake();
    $expectedTotalCredits = ($this->productOne->provides[0]->getAmount() * $cart->items[0]->quantity)
        + ($this->productTwo->provides[0]->getAmount() * $cart->items[1]->quantity);

    app(ProcessCart::class)->execute($cart);

    expect($billable->credits()->balance())->toBe($expectedTotalCredits);

    // now, let's process the cart again, (maybe by accident), and
    // make sure the credits are not added twice.
    app(ProcessCart::class)->execute($cart);

    expect($billable->credits()->balance())->toBe($expectedTotalCredits);
});

test('ProcessCart does not add the credits if the payment fails', function () {
    $user = createBillable();
    $cart = Cart::factory()
        ->for($user, 'billable')
        ->has(CartItem::factory(1, ['product_id' => $this->productOne->id]), 'items')
        ->create();
    expect($user->credits()->balance())->toBe(0);
    PaymentGateway::shouldReceive('billable')->andReturnSelf()
        ->shouldReceive('payForCart')->andThrow(\Exception::class, 'invalid payment')
        ->shouldReceive('provider')->andReturn(PaymentProvider::Stripe);

    try {
        app(ProcessCart::class)->execute($cart);
    } catch (\Exception $e) {}

    expect($user->credits()->balance())->toBe(0);
});

test('ProcessCart fires a ProductPurchased event for every product purchased', function () {
    $cart = Cart::factory()->for($billable = createBillable(), 'billable')
        ->has(CartItem::factory(1, ['product_id' => $this->productOne->id]), 'items')
        ->has(CartItem::factory(1, ['product_id' => $this->productTwo->id]), 'items')
        ->create();
    $firstItem = $cart->items[0];
    $secondItem = $cart->items[1];
    Event::fake([ProductPurchased::class]);
    PaymentGateway::fake();

    app(ProcessCart::class)->execute($cart);

    Event::assertDispatched(ProductPurchased::class, function (ProductPurchased $event) use ($firstItem, $billable) {
        return $event->product instanceof Product
            && $event->product->id === $this->productOne->id
            && $event->quantity === $firstItem->quantity
            && $event->billable->is($billable);
    });
    Event::assertDispatched(ProductPurchased::class, function (ProductPurchased $event) use ($secondItem, $billable) {
        return $event->product instanceof Product
            && $event->product->id === $this->productTwo->id
            && $event->quantity === $secondItem->quantity
            && $event->billable->is($billable);
    });
})->skip('TODO: rework this test with the new behaviour of events after processing cart.');
