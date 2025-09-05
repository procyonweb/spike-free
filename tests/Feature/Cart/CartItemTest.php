<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Opcodes\Spike\CartItem;
use Opcodes\Spike\CreditAmount;
use Opcodes\Spike\Tests\Fixtures\SampleProvidable;

uses(RefreshDatabase::class);

it('it can calculate the total provides, combined', function () {
    config(['spike.products' => [
        $product = [
            'id' => 'standard',
            'name' => 'standard',
            'provides' => [
                CreditAmount::make(500),
                CreditAmount::make(100, 'sms'),
            ]
        ]
    ]]);

    $item = CartItem::factory()->create([
        'product_id' => $product['id'],
        'quantity' => 2,
    ]);

    expect($item->totalProvides())
        ->toBeInstanceOf(Collection::class)
        ->and($item->totalProvides()->count())->toBe(2)
        ->and($item->totalProvides()->first()->getAmount())->toBe(1000)
        ->and($item->totalProvides()->first()->getType()->type)->toBe('credits')
        ->and($item->totalProvides()->last()->getAmount())->toBe(200)
        ->and($item->totalProvides()->last()->getType()->type)->toBe('sms');
});

it('keeps only unique providables that are not countable', function () {
    $providable1 = new SampleProvidable();
    $providable2 = new class extends SampleProvidable {};

    config(['spike.products' => [
        $product = [
            'id' => 'standard',
            'name' => 'standard',
            'provides' => [
                $providable1,
                $providable2,
            ]
        ]
    ]]);

    $item = CartItem::factory()->create([
        'product_id' => $product['id'],
        'quantity' => 2,
    ]);

    expect($item->totalProvides())
        ->toBeInstanceOf(Collection::class)
        ->and($item->totalProvides()->count())->toBe(2)
        ->and($item->totalProvides()->get(0))->toBe($providable1)
        ->and($item->totalProvides()->get(1))->toBe($providable2);
});
