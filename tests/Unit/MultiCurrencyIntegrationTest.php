<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Opcodes\Spike\Cart;
use Opcodes\Spike\CreditAmount;
use Opcodes\Spike\Facades\Spike;
use Opcodes\Spike\Product;

uses(RefreshDatabase::class);

test('real world multi-currency scenario with custom resolver', function () {
    config(['cashier.currency' => 'usd']);
    
    // Simulate user's scenario where they have products in database and determine currency based on user
    $billable = createBillable();
    $billable->currency = 'eur'; // User's preferred currency
    Spike::resolve(fn() => $billable);
    
    // Helper function to simulate user's pricing logic
    $getPriceForUser = function($product, $billable) {
        // Simulate currency-based pricing
        $basePriceUsd = match($product['slug']) {
            'plus-pack' => 1000, // $10.00 USD
            'pro-pack' => 2500,  // $25.00 USD
            default => 1000,
        };
        
        // Convert to user's currency (simplified conversion)
        $conversionRates = ['usd' => 1.0, 'eur' => 0.85, 'gbp' => 0.75];
        $userCurrency = $billable->currency ?? 'usd';
        
        return (int) ($basePriceUsd * $conversionRates[$userCurrency]);
    };
    
    // Mock the user's resolver logic 
    Spike::resolveProductsUsing(function ($billableInstance) use ($getPriceForUser) {
        // Simulate getting products from database
        $mockProducts = [
            ['slug' => 'plus-pack', 'name' => 'Plus Pack', 'credits' => 1000, 'stripe_price_id' => 'price_123'],
            ['slug' => 'pro-pack', 'name' => 'Pro Pack', 'credits' => 2500, 'stripe_price_id' => 'price_456'],
        ];
        
        $products = [];
        
        foreach ($mockProducts as $product) {
            // User's logic to determine price based on currency
            $priceInCents = $getPriceForUser($product, $billableInstance);
            $userCurrency = $billableInstance->currency ?? 'usd';
            
            $products[] = new Product(
                id: $product['slug'],
                name: $product['name'],
                provides: [
                    CreditAmount::make($product['credits'])->expiresAfterMonths(12),
                ],
                payment_provider_price_id: $product['stripe_price_id'],
                price_in_cents: $priceInCents,
                currency: $userCurrency, // The key addition for multi-currency support
            );
        }
        
        return $products;
    });
    
    // Test that products are resolved with correct currency
    $products = Spike::products();

    expect($products)->toHaveCount(2)
        ->and($products->first()->currency)->toBe('eur')
        ->and($products->last()->currency)->toBe('eur')
        ->and($products->first()->id)->toBe('plus-pack')
        ->and($products->last()->id)->toBe('pro-pack');
    
    // Test that cart validation works correctly
    $cart = Cart::factory()->forBillable($billable)->create();
    $cart->items()->createMany([
        ['product_id' => 'plus-pack', 'quantity' => 1],
        ['product_id' => 'pro-pack', 'quantity' => 2],
    ]);
    
    // Since all products have same currency (EUR), validation should pass
    $paymentGateway = new \Opcodes\Spike\Stripe\PaymentGateway();
    $paymentGateway->billable($billable);

    $cart = $cart->fresh('items');
    // Let's fill in the cart/billable relation to avoid reloading the billable from the database, since we
    // don't have a way to actually set currency on the billable model in our tests.
    $cart->setRelation('items', $cart->items->map(function ($item) use ($cart, $billable) {
        return $item->setRelation('cart', $cart->withoutRelations()->setRelation('billable', $billable));
    }));
    $cartCurrency = $cart->validateAndDetermineCurrency();
    
    expect($cartCurrency)->toBe('eur');
});

test('mixed currency scenario fails correctly', function () {
    $billable = createBillable();
    
    // Resolver that returns products with different currencies (this should fail at cart level)
    Spike::resolveProductsUsing(function () {
        return [
            new Product(
                id: 'usd-product',
                name: 'USD Product',
                payment_provider_price_id: 'price_usd',
                currency: 'usd',
                price_in_cents: 1000,
                provides: [CreditAmount::make(500)]
            ),
            new Product(
                id: 'eur-product', 
                name: 'EUR Product',
                payment_provider_price_id: 'price_eur',
                currency: 'eur',
                price_in_cents: 850,
                provides: [CreditAmount::make(500)]
            )
        ];
    });
    
    Spike::resolve(fn() => $billable);
    
    // Products can be resolved with different currencies
    $products = Spike::products();
    expect($products)->toHaveCount(2)
        ->and($products->first()->currency)->toBe('usd')
        ->and($products->last()->currency)->toBe('eur');
    
    // But cart validation should fail when mixed currencies are added
    $cart = Cart::factory()->forBillable($billable)->create();
    $cart->items()->createMany([
        ['product_id' => 'usd-product', 'quantity' => 1],
        ['product_id' => 'eur-product', 'quantity' => 1],
    ]);
    
    $paymentGateway = new \Opcodes\Spike\Stripe\PaymentGateway();
    $paymentGateway->billable($billable);
    
    $cart = $cart->fresh('items');
    // Let's fill in the cart/billable relation to avoid reloading the billable from the database, since we
    // don't have a way to actually set currency on the billable model in our tests.
    $cart->setRelation('items', $cart->items->map(function ($item) use ($cart, $billable) {
        return $item->setRelation('cart', $cart->withoutRelations()->setRelation('billable', $billable));
    }));
    
    expect(fn() => $cart->validateAndDetermineCurrency())
        ->toThrow(InvalidArgumentException::class, 'Cart contains products with different currencies');
}); 