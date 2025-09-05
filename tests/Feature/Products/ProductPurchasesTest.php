<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Opcodes\Spike\Cart;
use Opcodes\Spike\CartItem;
use Opcodes\Spike\CreditAmount;
use Opcodes\Spike\Facades\Spike;
use Opcodes\Spike\Product;

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

it('can check whether a product has been purchased', function () {
    $billable = createBillable();

    Cart::factory()
        ->for($billable, 'billable')
        ->has(CartItem::factory(1, ['product_id' => $this->productOne->id]), 'items')
        ->paid()
        ->create();

    expect($billable->hasPurchased($this->productOne))->toBeTrue()
        ->and($billable->hasPurchased($this->productOne->id))->toBeTrue()
        ->and($billable->hasPurchased($this->productTwo))->toBeFalse()
        ->and($billable->hasPurchased($this->productTwo->id))->toBeFalse();
});

it('can get a grouped list of all product purchases', function () {
    $billable = createBillable();

    $firstCart = Cart::factory()
        ->for($billable, 'billable')
        ->has(CartItem::factory(1, ['product_id' => $this->productOne->id, 'quantity' => 1]), 'items')
        ->has(CartItem::factory(1, ['product_id' => $this->productTwo->id, 'quantity' => 1]), 'items')
        ->paid()
        ->create();
    $secondCart = Cart::factory()
        ->for($billable, 'billable')
        ->has(CartItem::factory(1, ['product_id' => $this->productOne->id, 'quantity' => 2]), 'items')
        ->paid()
        ->create();
    // and this one's unpaid, thus should not be included in the results.
    Cart::factory()
        ->for($billable, 'billable')
        ->has(CartItem::factory(1, ['product_id' => $this->productOne->id, 'quantity' => 1]), 'items')
        ->create();

    expect($billable->groupedPurchases())->toBeInstanceOf(\Illuminate\Support\Collection::class)
        ->toHaveCount(2);

    $firstProduct = $billable->groupedPurchases()->where('product.id', $this->productOne->id)->first();
    expect($firstProduct)->toBeInstanceOf(\Opcodes\Spike\GroupedProductPurchase::class)
        ->and($firstProduct->product)->toBe($this->productOne)
        ->and($firstProduct->quantity)->toBe(3)
        ->and($firstProduct->first_purchase_at)->toEqual($firstCart->paid_at)
        ->and($firstProduct->last_purchase_at)->toEqual($secondCart->paid_at);

    $secondProduct = $billable->groupedPurchases()->where('product.id', $this->productTwo->id)->first();
    expect($secondProduct)->toBeInstanceOf(\Opcodes\Spike\GroupedProductPurchase::class)
        ->and($secondProduct->product)->toBe($this->productTwo)
        ->and($secondProduct->quantity)->toBe(1)
        ->and($secondProduct->first_purchase_at)->toEqual($firstCart->paid_at)
        ->and($secondProduct->last_purchase_at)->toEqual($firstCart->paid_at);
});

it('can get ungrouped purchases', function () {
    $billable = createBillable();

    $firstCart = Cart::factory()
        ->for($billable, 'billable')
        ->has(CartItem::factory(1, ['product_id' => $this->productOne->id, 'quantity' => 1]), 'items')
        ->has(CartItem::factory(1, ['product_id' => $this->productTwo->id, 'quantity' => 1]), 'items')
        ->paid()
        ->create();
    $secondCart = Cart::factory()
        ->for($billable, 'billable')
        ->has(CartItem::factory(1, ['product_id' => $this->productOne->id, 'quantity' => 2]), 'items')
        ->paid()
        ->create();
    // and this one's unpaid, thus should not be included in the results.
    Cart::factory()
        ->for($billable, 'billable')
        ->has(CartItem::factory(1, ['product_id' => $this->productOne->id, 'quantity' => 1]), 'items')
        ->create();

    expect($billable->purchases())->toBeInstanceOf(\Illuminate\Support\Collection::class)
        ->toHaveCount(3);

    $firstProductPurchase = $billable->purchases()->first();
    expect($firstProductPurchase)->toBeInstanceOf(\Opcodes\Spike\ProductPurchase::class)
        ->and($firstProductPurchase->product)->toBe($this->productOne)
        ->and($firstProductPurchase->quantity)->toBe(1)
        ->and($firstProductPurchase->purchased_at)->toEqual($firstCart->paid_at);

    $secondProductPurchase = $billable->purchases()->skip(1)->first();
    expect($secondProductPurchase)->toBeInstanceOf(\Opcodes\Spike\ProductPurchase::class)
        ->and($secondProductPurchase->product)->toBe($this->productTwo)
        ->and($secondProductPurchase->quantity)->toBe(1)
        ->and($secondProductPurchase->purchased_at)->toEqual($firstCart->paid_at);

    $thirdProductPurchase = $billable->purchases()->skip(2)->first();
    expect($thirdProductPurchase)->toBeInstanceOf(\Opcodes\Spike\ProductPurchase::class)
        ->and($thirdProductPurchase->product)->toBe($this->productOne)
        ->and($thirdProductPurchase->quantity)->toBe(2)
        ->and($thirdProductPurchase->purchased_at)->toEqual($secondCart->paid_at);
});

it('can get purchased product that is archived', function () {
    $billable = createBillable();

    $archivedProduct = new Product(
        id: 'archived',
        name: 'archived',
        payment_provider_price_id: 'archived_stripe_id',
        price_in_cents: 3000,
        provides: [CreditAmount::make(1500)],
        archived: true
    );
    Spike::resolveProductsUsing(fn () => [$this->productOne, $this->productTwo, $archivedProduct]);

    Cart::factory()
        ->for($billable, 'billable')
        ->has(CartItem::factory(1, ['product_id' => $archivedProduct->id]), 'items')
        ->paid()
        ->create();

    expect($billable->hasPurchased($archivedProduct))->toBeTrue();
});

it('can get purchased product that is no longer defined in config', function () {
    $billable = createBillable();

    Cart::factory()
        ->for($billable, 'billable')
        ->has(CartItem::factory(1, ['product_id' => 'non_existent_product']), 'items')
        ->paid()
        ->create();

    expect($billable->hasPurchased('non_existent_product'))->toBeTrue();
});
