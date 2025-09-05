<?php


use Opcodes\Spike\CreditAmount;
use Opcodes\Spike\Facades\Spike;
use Opcodes\Spike\Product;
use Illuminate\Support\Collection;

test('Spike::products() returns a list of products to purchase', function () {
    config(['spike.products' => null]);
    // if not configured, it's empty.
    expect(Spike::products())->toBeEmpty();

    // now let's configure some products
    config(['spike.products' => [
        $data = [
            'id' => '10_dollars',
            'name' => 'Standard pack',
            'short_description' => 'For varying use',
            'payment_provider_price_id' => 'price_id_12345',
            'price_in_cents' => 1000,
            'provides' => [
                CreditAmount::make(5_000)->expiresAfterMonths(6),
            ],
        ]
    ]]);

    expect(Spike::products())
        ->not->toBeEmpty()
        ->toBeInstanceOf(Collection::class)

        ->and(Spike::products()->first())
        ->toBeInstanceOf(Product::class)
        ->id->toBe($data['id'])
        ->name->toBe($data['name'])
        ->short_description->toBe($data['short_description'])
        ->payment_provider_price_id->toBe($data['payment_provider_price_id'])
        ->price_in_cents->toBe($data['price_in_cents'])
        ->provides->toBe($data['provides']);
});

test('Spike::findProduct() finds the correct Product', function () {
    config(['spike.products' => [
        $firstBundle = [
            'id' => '10_dollars',
            'name' => 'Standard pack',
            'payment_provider_price_id' => 'price_id_12345',
            'provides' => [
                CreditAmount::make(5_000),
            ]
        ],
        $secondBundle = [
            'id' => '20_dollars',
            'name' => 'Standard pack',
            'payment_provider_price_id' => 'price_id_85763',
            'provides' => [
                CreditAmount::make(10_000),
            ],
        ],
    ]]);

    expect(Spike::findProduct($secondBundle['id']))
        ->not->toBeNull()
        ->toBeInstanceOf(Product::class)
        ->id->toBe($secondBundle['id'])
        ->payment_provider_price_id->toBe($secondBundle['payment_provider_price_id'])
        ->provides->toBe($secondBundle['provides'])

        ->and(Spike::findProduct($firstBundle['id']))
        ->not->toBeNull()
        ->toBeInstanceOf(Product::class)
        ->id->toBe($firstBundle['id'])
        ->payment_provider_price_id->toBe($firstBundle['payment_provider_price_id'])
        ->provides->toBe($firstBundle['provides'])

        ->and(Spike::findProduct('sdfsdfgsdfgsdfg'))
        ->toBeNull();
});

test('Spike::productsAvailable() returns whether there are any products available', function () {
    config(['spike.products' => []]);

    expect(Spike::productsAvailable())
        ->toBeFalse();

    config(['spike.products' => [
        [
            'id' => '20_dollars',
            'name' => 'Standard pack',
            'payment_provider_price_id' => 'price_id_85763',
            'provides' => [
                CreditAmount::make(10_000),
            ],
        ]
    ]]);

    expect(Spike::productsAvailable())
        ->toBeTrue();
});

test('Spike::resolveProductsUsing() callback provides a billable instance to the callback', function () {
    $user = createBillable();
    Spike::resolve(fn () => $user);
    $resolvedCorrectly = false;
    Spike::resolveProductsUsing(function ($billable) use (&$resolvedCorrectly, $user) {
        if ($billable && $billable->is($user)) {
            $resolvedCorrectly = true;
        }

        return [];
    });

    // resolve the products to trigger the callback
    $products = Spike::products();

    expect($products)->toBeEmpty()
        ->and($resolvedCorrectly)->toBeTrue();
});

test('Spike::resolveProductsUsing() changes the Spike products resolved', function () {
    config(['spike.products' => null]);
    expect(Spike::products())->toBeEmpty();
    $product = new Product(
        id: 'test_product',
        name: 'test product',
        payment_provider_price_id: 'price_id',
        provides: [
            CreditAmount::make(5_000),
        ]
    );

    Spike::resolveProductsUsing(function () use ($product) {
        return [$product];
    });

    expect(Spike::products())->not->toBeEmpty()
        ->and(Spike::products()->first())
        ->id->toBe($product->id)
        ->name->toBe($product->name)
        ->provides->toBe($product->provides)
        ->payment_provider_price_id->toBe($product->payment_provider_price_id);
});

test('Spike::resolveProductsUsing() works with different billables', function () {
    config(['spike.products' => null]);
    $first = new Product(
        id: 'first_product',
        name: 'first product',
        payment_provider_price_id: 'price_id',
    );
    $second = new Product(
        id: 'second_product',
        name: 'second product',
        payment_provider_price_id: 'price_id',
    );

    $secondBillable = 'second_billable_class';
    Spike::resolveProductsUsing(fn () => [$first]);
    Spike::billable($secondBillable)->resolveProductsUsing(fn () => [$second]);

    expect(Spike::products())->toHaveCount(1)
        ->and(Spike::products()->first())
        ->id->toBe($first->id)

        ->and(Spike::billable($secondBillable)->products())
        ->toHaveCount(1)

        ->and(Spike::billable($secondBillable)->products()->first())
        ->id->toBe($second->id)

        // and again calling it without billable should return the base products
        ->and(Spike::products()->first())
        ->id->toBe($first->id);
});

test('Spike::resolveProductsUsing() allows providing array config instead of Product objects', function () {
    config(['spike.products' => null]);
    $first = [
        'id' => 'first_product',
        'name' => 'first product',
        // 'credits' => 5000,
        'payment_provider_price_id' => 'price_id',
        'provides' => [
            CreditAmount::make(5_000),
        ]
    ];

    Spike::resolveProductsUsing(fn () => [$first]);

    expect(Spike::products())->toHaveCount(1)
        ->and(Spike::products()->first())
        ->toBeInstanceOf(Product::class)
        ->id->toBe($first['id'])
        ->name->toBe($first['name'])
        ->provides->toBe($first['provides'])
        ->payment_provider_price_id->toBe($first['payment_provider_price_id']);
});

test('product provides must implement the ProvidableContract interface', function () {
    config(['spike.products' => [[
        'id' => 'first_product',
        'name' => 'first product',
        'payment_provider_price_id' => 'price_id',
        'provides' => [
            new stdClass(),
        ]
    ]]]);

    Spike::products();
})->throws(
    InvalidArgumentException::class,
    "The class " . get_class(new stdClass()) . " must implement the " . \Opcodes\Spike\Contracts\Providable::class . " interface."
);

test('backwards compatible with v2 configuration', function () {
    config(['spike.products' => [
        $data = [
            'id' => '10_dollars',
            'name' => 'Standard pack',
            'short_description' => 'For varying use',
            'payment_provider_price_id' => 'price_id_12345',
            'price_in_cents' => 1000,
            'credits' => 5_000,
            'expires_after' => \Carbon\CarbonInterval::months(6),
        ]
    ]]);

    expect(Spike::products())
        ->not->toBeEmpty()
        ->toBeInstanceOf(Collection::class)

        ->and(Spike::products()->first())
        ->toBeInstanceOf(Product::class)
        ->id->toBe($data['id'])
        ->name->toBe($data['name'])
        ->short_description->toBe($data['short_description'])
        ->payment_provider_price_id->toBe($data['payment_provider_price_id'])
        ->price_in_cents->toBe($data['price_in_cents'])
        ->provides->toEqual([
            CreditAmount::make($data['credits'])->expiresAfterMonths(6),
        ]);
});

it('does not return archived products by default', function () {
    config(['spike.products' => [
        $firstBundle = [
            'id' => '10_dollars',
            'name' => 'Standard pack',
            'payment_provider_price_id' => 'price_id_12345',
            'provides' => [
                CreditAmount::make(5_000),
            ]
        ],
        $secondBundle = [
            'id' => '20_dollars',
            'name' => 'Standard pack',
            'payment_provider_price_id' => 'price_id_85763',
            'provides' => [
                CreditAmount::make(10_000),
            ],
            'archived' => true,
        ],
    ]]);

    expect(Spike::products())
        ->toHaveCount(1)
        ->and(Spike::products()->first())
        ->id->toBe($firstBundle['id'])

        ->and(Spike::findProduct($secondBundle['id']))
        ->toBeNull();
});

it('can return archived products', function () {
    config(['spike.products' => [
        $firstBundle = [
            'id' => '10_dollars',
            'name' => 'Standard pack',
            'payment_provider_price_id' => 'price_id_12345',
            'provides' => [
                CreditAmount::make(5_000),
            ]
        ],
        $secondBundle = [
            'id' => '20_dollars',
            'name' => 'Standard pack',
            'payment_provider_price_id' => 'price_id_85763',
            'provides' => [
                CreditAmount::make(10_000),
            ],
            'archived' => true,
        ],
    ]]);

    expect(Spike::products(includeArchived: true))
        ->toHaveCount(2)
        ->and(Spike::products(includeArchived: true)->first())
        ->id->toBe($firstBundle['id'])
        ->and(Spike::products(includeArchived: true)->last())
        ->id->toBe($secondBundle['id'])

        ->and(Spike::findProduct($secondBundle['id'], includeArchived: true))
        ->not->toBeNull()
        ->id->toBe($secondBundle['id']);
});

test('Spike::resolveProductsUsing() can set products with different currencies', function () {
    config(['spike.products' => null]);
    
    $usdProduct = new Product(
        id: 'usd_product',
        name: 'USD product',
        payment_provider_price_id: 'price_usd_123',
        currency: 'usd',
        price_in_cents: 1000,
    );
    
    $eurProduct = new Product(
        id: 'eur_product',
        name: 'EUR product',
        payment_provider_price_id: 'price_eur_456',
        currency: 'eur',
        price_in_cents: 900,
    );

    Spike::resolveProductsUsing(function () use ($usdProduct, $eurProduct) {
        return [$usdProduct, $eurProduct];
    });

    $products = Spike::products();
    
    expect($products)->toHaveCount(2)
        ->and($products->first()->currency)->toBe('usd')
        ->and($products->last()->currency)->toBe('eur');
});

test('Spike::resolveProductsUsing() can use array config with currency', function () {
    config(['spike.products' => null]);
    
    $productConfigs = [
        [
            'id' => 'multi_currency_product_1',
            'name' => 'Multi Currency Product 1',
            'payment_provider_price_id' => 'price_123',
            'currency' => 'usd',
            'price_in_cents' => 1500,
            'provides' => [
                CreditAmount::make(1000),
            ]
        ],
        [
            'id' => 'multi_currency_product_2',
            'name' => 'Multi Currency Product 2',
            'payment_provider_price_id' => 'price_456',
            'currency' => 'eur',
            'price_in_cents' => 1200,
            'provides' => [
                CreditAmount::make(800),
            ]
        ]
    ];

    Spike::resolveProductsUsing(function () use ($productConfigs) {
        return $productConfigs;
    });

    $products = Spike::products();
    
    expect($products)->toHaveCount(2)
        ->and($products->first())
        ->toBeInstanceOf(Product::class)
        ->currency->toBe('usd')
        ->and($products->last())
        ->toBeInstanceOf(Product::class)
        ->currency->toBe('eur');
});

test('Spike::resolveProductsUsing() callback can access billable to determine currency', function () {
    config(['spike.products' => null]);
    $user = createBillable();
    $user->currency = 'gbp'; // Assume user has a currency attribute
    Spike::resolve(fn () => $user);
    
    Spike::resolveProductsUsing(function ($billable) {
        // Use billable to determine currency (like the user's example)
        $userCurrency = $billable->currency ?? 'usd';
        
        return [
            [
                'id' => 'dynamic_currency_product',
                'name' => 'Dynamic Currency Product',
                'payment_provider_price_id' => 'price_123',
                'currency' => $userCurrency,
                'price_in_cents' => 1000,
                'provides' => [
                    CreditAmount::make(500),
                ]
            ]
        ];
    });

    $products = Spike::products();
    
    expect($products)->toHaveCount(1)
        ->and($products->first()->currency)->toBe('gbp');
});

test('Spike::resolveProductsUsing() works with null currency (backward compatibility)', function () {
    config(['spike.products' => null]);
    
    Spike::resolveProductsUsing(function () {
        return [
            [
                'id' => 'no_currency_product',
                'name' => 'No Currency Product',
                'payment_provider_price_id' => 'price_123',
                'price_in_cents' => 1000,
                'provides' => [
                    CreditAmount::make(500),
                ]
                // Note: no currency specified
            ]
        ];
    });

    $products = Spike::products();
    
    expect($products)->toHaveCount(1)
        ->and($products->first()->currency)->toBeNull();
});
