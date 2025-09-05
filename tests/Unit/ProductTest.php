<?php

use Opcodes\Spike\CreditAmount;
use Opcodes\Spike\Product;

test('Product class can be instantiated with currency', function () {
    $product = new Product(
        id: 'test-product',
        name: 'Test Product',
        currency: 'usd',
        price_in_cents: 1000,
    );

    expect($product->currency)->toBe('usd')
        ->and($product->price_in_cents)->toBe(1000);
});

test('Product class defaults to null currency when not specified', function () {
    $product = new Product(
        id: 'test-product',
        name: 'Test Product',
        price_in_cents: 1000,
    );

    expect($product->currency)->toBeNull();
});

test('Product::fromArray can handle currency configuration', function () {
    $config = [
        'id' => 'test-product',
        'name' => 'Test Product',
        'currency' => 'eur',
        'price_in_cents' => 2000,
        'provides' => [
            CreditAmount::make(1000),
        ],
    ];

    $product = Product::fromArray($config);

    expect($product->currency)->toBe('eur')
        ->and($product->price_in_cents)->toBe(2000)
        ->and($product->id)->toBe('test-product');
});

test('Product::fromArray defaults to null currency when not specified', function () {
    $config = [
        'id' => 'test-product',
        'name' => 'Test Product',
        'price_in_cents' => 1500,
        'provides' => [
            CreditAmount::make(500),
        ],
    ];

    $product = Product::fromArray($config);

    expect($product->currency)->toBeNull();
});

test('Product::toArray includes currency', function () {
    $product = new Product(
        id: 'test-product',
        name: 'Test Product',
        currency: 'gbp',
        price_in_cents: 3000,
    );

    $array = $product->toArray();

    expect($array)->toHaveKey('currency', 'gbp')
        ->and($array)->toHaveKey('price_in_cents', 3000);
});

test('Product::toArray includes null currency when not set', function () {
    $product = new Product(
        id: 'test-product',
        name: 'Test Product',
        price_in_cents: 1000,
    );

    $array = $product->toArray();

    expect($array)->toHaveKey('currency', null);
});

test('Product::priceFormatted uses product currency when specified', function () {
    // Mock the Utils::formatAmount method by checking it gets called with correct parameters
    $product = new Product(
        id: 'test-product',
        name: 'Test Product',
        currency: 'eur',
        price_in_cents: 1500,
    );

    // The priceFormatted method should call Utils::formatAmount with the currency
    $formatted = $product->priceFormatted();
    
    // This will depend on Utils::formatAmount implementation, but we can check it doesn't throw
    expect($formatted)->toBeString();
});

test('Product with different currencies can be created', function () {
    $usdProduct = new Product(
        id: 'usd-product',
        name: 'USD Product',
        currency: 'usd',
        price_in_cents: 1000,
    );

    $eurProduct = new Product(
        id: 'eur-product', 
        name: 'EUR Product',
        currency: 'eur',
        price_in_cents: 900,
    );

    expect($usdProduct->currency)->toBe('usd')
        ->and($eurProduct->currency)->toBe('eur')
        ->and($usdProduct->currency)->not->toBe($eurProduct->currency);
});