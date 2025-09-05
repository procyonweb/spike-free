<?php

use Opcodes\Spike\Actions\Products\ProcessCart;
use Opcodes\Spike\Cart;
use Opcodes\Spike\CartItem;
use Opcodes\Spike\Facades\Spike;
use Opcodes\Spike\Http\Livewire\CheckoutModal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

test('CheckoutModal::pay() executes the ProcessCart action', closure: function () {
    config(['spike.products' => [$product = ['id' => 'standard', 'name' => 'standard']]]);
    $billable = createBillable();
    Spike::resolve(fn () => $billable);
    $cart = Cart::factory()
        ->for($billable, 'billable')
        ->has(CartItem::factory(1, ['product_id' => $product['id']]), 'items')
        ->create();

    $this->mock(
        ProcessCart::class,
        fn (MockInterface $mock) => $mock->shouldReceive('execute')
            ->with(Mockery::on(fn ($arg) => $arg instanceof Cart && $arg->id === $cart->id))
            ->once()
            ->andReturn(true)
    );

    Livewire::test(CheckoutModal::class)
        ->set('cartId', $cart->id)
        ->call('pay');
});
