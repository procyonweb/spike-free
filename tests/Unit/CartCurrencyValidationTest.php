<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Opcodes\Spike\Cart;
use Opcodes\Spike\CreditAmount;
use Opcodes\Spike\Facades\Spike;
use Opcodes\Spike\Product;
use Opcodes\Spike\Utils;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->billable = createBillable();
});

test('cart can add products with same currency', function () {
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
    
    // Should be able to add both products
    $cart->addProduct('product_1', 1);
    $cart->addProduct('product_2', 2);
    
    $cart->refresh();
    expect($cart->items)->toHaveCount(2)
        ->and($cart->currency())->toBe('usd');
});

test('cart rejects adding products with different currencies', function () {
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
    
    // Add first product
    $cart->addProduct('usd_product', 1);
    expect($cart->currency())->toBe('usd');
    
    // Should throw exception when adding product with different currency
    expect(fn() => $cart->addProduct('eur_product', 1))
        ->toThrow(InvalidArgumentException::class, "Cannot add product with currency 'eur' to cart with currency 'usd'");
});

test('cart allows adding products with null currency to products with specified currency', function () {
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
    
    // Add USD product first
    $cart->addProduct('usd_product', 1);
    expect($cart->currency())->toBe('usd');
    
    // Should be able to add null currency product
    $cart->addProduct('no_currency_product', 1);
    $cart->refresh();
    expect($cart->items)->toHaveCount(2)
        ->and($cart->currency())->toBe('usd');
});

test('cart allows adding products with specified currency to products with null currency', function () {
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
    
    // Add null currency product first
    $cart->addProduct('no_currency_product', 1);
    expect($cart->currency())->toBeNull();
    
    // Should be able to add USD product
    $cart->addProduct('usd_product', 1);
    $cart->refresh();
    expect($cart->items)->toHaveCount(2)
        ->and($cart->currency())->toBe('usd');
});

test('cart currency validation is case insensitive', function () {
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
    
    // Should be able to add both products despite case differences
    $cart->addProduct('product_1', 1);
    $cart->addProduct('product_2', 1);
    
    $cart->refresh();
    expect($cart->items)->toHaveCount(2)
        ->and($cart->currency())->toBe('usd'); // Should normalize to lowercase
});

test('cart allows adding products with no currency', function () {
    $product1 = new Product(
        id: 'product_1',
        name: 'Product 1',
        payment_provider_price_id: 'price_123',
        price_in_cents: 1000,
        provides: [CreditAmount::make(500)]
        // Note: no currency specified
    );
    
    $product2 = new Product(
        id: 'product_2',
        name: 'Product 2',
        payment_provider_price_id: 'price_456',
        price_in_cents: 2000,
        provides: [CreditAmount::make(1000)]
        // Note: no currency specified
    );

    Spike::resolveProductsUsing(fn() => [$product1, $product2]);

    $cart = Cart::factory()->forBillable($this->billable)->create();
    
    // Should be able to add both products
    $cart->addProduct('product_1', 1);
    $cart->addProduct('product_2', 1);
    
    $cart->refresh();
    expect($cart->items)->toHaveCount(2)
        ->and($cart->currency())->toBeNull();
});

test('cart currency method returns null for empty cart', function () {
    $cart = Cart::factory()->forBillable($this->billable)->create();
    
    expect($cart->currency())->toBeNull();
});

test('cart currency method returns null when all products have null currency', function () {
    $product = new Product(
        id: 'product_1',
        name: 'Product 1',
        payment_provider_price_id: 'price_123',
        price_in_cents: 1000,
        provides: [CreditAmount::make(500)]
        // Note: no currency specified
    );

    Spike::resolveProductsUsing(fn() => [$product]);

    $cart = Cart::factory()->forBillable($this->billable)->create();
    $cart->addProduct('product_1', 1);
    
    expect($cart->currency())->toBeNull();
});

test('cart price formatting uses cart currency', function () {
    config(['cashier.currency' => 'usd']);
    $originalProduct = new Product(
        id: 'product_1',
        name: 'Product 1',
        payment_provider_price_id: 'price_123',
        price_in_cents: 1000,
        provides: [CreditAmount::make(500)]
    );
    $originalPriceFormatted = Utils::formatAmount($originalProduct->price_in_cents);
    
    $product = new Product(
        id: 'eur_product',
        name: 'EUR Product',
        payment_provider_price_id: 'price_eur',
        currency: 'eur',
        price_in_cents: 1000,
        provides: [CreditAmount::make(500)]
    );

    Spike::resolveProductsUsing(fn() => [$product]);

    $cart = Cart::factory()->forBillable($this->billable)->create();
    $cart->addProduct('eur_product', 1);
    
    $formatted = $cart->totalPriceFormatted();
    $formattedWithDiscount = $cart->totalPriceAfterDiscountFormatted();
    
    expect($formatted)->toBeString()->not->toBe($originalPriceFormatted);
    expect($formattedWithDiscount)->toBeString()->not->toBe($originalPriceFormatted);
});

test('cart item price formatting uses cart currency', function () {
    config(['cashier.currency' => 'usd']);
    $originalProduct = new Product(
        id: 'product_1',
        name: 'Product 1',
        payment_provider_price_id: 'price_123',
        price_in_cents: 1000,
        provides: [CreditAmount::make(500)]
    );
    $originalPriceFormatted = Utils::formatAmount($originalProduct->price_in_cents);
    
    $product = new Product(
        id: 'eur_product',
        name: 'EUR Product',
        payment_provider_price_id: 'price_eur',
        currency: 'eur',
        price_in_cents: 1000,
        provides: [CreditAmount::make(500)]
    );

    Spike::resolveProductsUsing(fn() => [$product]);

    $cart = Cart::factory()->forBillable($this->billable)->create();
    $cart->addProduct('eur_product', 2);
    
    $cartItem = $cart->items->first();
    $formatted = $cartItem->totalPriceFormatted();
    
    expect($formatted)->toBeString()->not->toBe($originalPriceFormatted);
}); 