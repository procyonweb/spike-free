<?php

use Opcodes\Spike\CartItem;
use Opcodes\Spike\CreditAmount;
use Opcodes\Spike\CreditTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Spatie\PestPluginTestTime\testTime;

uses(RefreshDatabase::class);

test('CreditTransaction::expire() does nothing when trying to expire a non-existent usage transaction', function () {
    $transaction = CreditTransaction::factory()->make(['expires_at' => null, 'credits' => 0]);
    expect($transaction->exists)->toBeFalse();

    $transaction->expire();

    expect($transaction->exists)->toBeFalse()
        ->and(CreditTransaction::count())->toBe(0);
});

test('CreditTransaction::expire() does nothing when trying to expire an empty usage transaction', function () {
    $transaction = CreditTransaction::factory()
        ->forBillable(createBillable())
        ->create(['expires_at' => null, 'credits' => 0]);
    expect($transaction)
        ->exists->toBeTrue()
        ->expired()->toBeFalse();

    $transaction->expire();

    expect($transaction)->expired()->toBeFalse();
});

test('CreditTransaction::expire() should reset the usage cache', function () {
    $user = createBillable();
    $user->credits()->add(500);
    $user->credits()->spend(100);
    $user->credits()->currentUsageTransaction()->expire();

    $user->credits()->spend(50);

    expect($user->credits()->currentUsageTransaction())
        ->credits->toBe(-50);
});
