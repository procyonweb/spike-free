<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Opcodes\Spike\Cart;
use Opcodes\Spike\CreditAmount;
use Opcodes\Spike\Facades\Spike;
use Opcodes\Spike\Product;
use Opcodes\Spike\Stripe\PaymentGateway;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->billable = createBillable();
    $this->paymentGateway = new PaymentGateway();
    $this->paymentGateway->billable($this->billable);
});

test('Cart can handle cart with single currency products', function () {
    // Create products with same currency
    $product1 = new Product(
        id: 'product_1',
        name: 'Product 1',
        payment_provider_price_id: 'price_123',
        currency: 'usd',
        price_in_cents: 1000,
        provides: [CreditAmount::make(500)]
    );
    
    $product2 = new Product(
        id: 'product_2', 
        name: 'Product 2',
        payment_provider_price_id: 'price_456',
        currency: 'usd',
        price_in_cents: 2000,
        provides: [CreditAmount::make(1000)]
    );

    Spike::resolveProductsUsing(fn() => [$product1, $product2]);

    $cart = Cart::factory()->forBillable($this->billable)->create();
    $cart->items()->createMany([
        ['product_id' => 'product_1', 'quantity' => 1],
        ['product_id' => 'product_2', 'quantity' => 2],
    ]);

    $result = $cart->fresh('items')->validateAndDetermineCurrency();
    expect($result)->toBe('usd');
});

test('Cart fails when cart contains products with different currencies', function () {
    // Create products with different currencies
    $usdProduct = new Product(
        id: 'usd_product',
        name: 'USD Product',
        payment_provider_price_id: 'price_usd',
        currency: 'usd',
        price_in_cents: 1000,
        provides: [CreditAmount::make(500)]
    );
    
    $eurProduct = new Product(
        id: 'eur_product',
        name: 'EUR Product', 
        payment_provider_price_id: 'price_eur',
        currency: 'eur',
        price_in_cents: 900,
        provides: [CreditAmount::make(450)]
    );

    Spike::resolveProductsUsing(fn() => [$usdProduct, $eurProduct]);

    $cart = Cart::factory()->forBillable($this->billable)->create();
    $cart->items()->createMany([
        ['product_id' => 'usd_product', 'quantity' => 1],
        ['product_id' => 'eur_product', 'quantity' => 1],
    ]);

    // Should throw exception due to mixed currencies
    expect(fn() => $this->paymentGateway->payForCart($cart))
        ->toThrow(InvalidArgumentException::class, 'Cart contains products with different currencies');
});

test('Cart handles cart with no currency products - backward compatibility', function () {
    $product = new Product(
        id: 'no_currency_product',
        name: 'No Currency Product',
        payment_provider_price_id: 'price_123',
        price_in_cents: 1000,
        provides: [CreditAmount::make(500)]
        // Note: no currency specified
    );

    Spike::resolveProductsUsing(fn() => [$product]);

    $cart = Cart::factory()->forBillable($this->billable)->create();
    $cart->items()->create(['product_id' => 'no_currency_product', 'quantity' => 1]);

    $result = $cart->fresh('items')->validateAndDetermineCurrency();
    expect($result)->toBeNull();
});

test('Cart handles mixed null and specified currencies', function () {
    $noCurrencyProduct = new Product(
        id: 'no_currency_product',
        name: 'No Currency Product',
        payment_provider_price_id: 'price_123',
        price_in_cents: 1000,
        provides: [CreditAmount::make(500)]
        // Note: no currency specified
    );
    
    $usdProduct = new Product(
        id: 'usd_product',
        name: 'USD Product',
        payment_provider_price_id: 'price_456',
        currency: 'usd',
        price_in_cents: 1500,
        provides: [CreditAmount::make(750)]
    );

    Spike::resolveProductsUsing(fn() => [$noCurrencyProduct, $usdProduct]);

    $cart = Cart::factory()->forBillable($this->billable)->create();
    $cart->items()->createMany([
        ['product_id' => 'no_currency_product', 'quantity' => 1],
        ['product_id' => 'usd_product', 'quantity' => 1],
    ]);

    $result = $cart->fresh('items')->validateAndDetermineCurrency();
    expect($result)->toBe('usd');
});

test('Cart currency validation is case insensitive', function () {
    $product1 = new Product(
        id: 'product_1',
        name: 'Product 1',
        payment_provider_price_id: 'price_123',
        currency: 'USD', // Uppercase
        price_in_cents: 1000,
        provides: [CreditAmount::make(500)]
    );
    
    $product2 = new Product(
        id: 'product_2',
        name: 'Product 2',
        payment_provider_price_id: 'price_456', 
        currency: 'usd', // Lowercase
        price_in_cents: 2000,
        provides: [CreditAmount::make(1000)]
    );

    Spike::resolveProductsUsing(fn() => [$product1, $product2]);

    $cart = Cart::factory()->forBillable($this->billable)->create();
    $cart->items()->createMany([
        ['product_id' => 'product_1', 'quantity' => 1],
        ['product_id' => 'product_2', 'quantity' => 1],
    ]);

    $result = $cart->fresh('items')->validateAndDetermineCurrency();
    expect($result)->toBe('usd'); // Should normalize to lowercase
});
