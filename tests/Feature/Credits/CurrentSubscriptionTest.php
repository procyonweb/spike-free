<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Opcodes\Spike\CreditTransaction;
use Opcodes\Spike\Facades\Credits;
use Opcodes\Spike\Facades\Spike;

uses(RefreshDatabase::class);

beforeEach(function () {
    // We resolve the billable into a fake user. It's ok, because we don't call any methods on the actual user.
    $fakeUser = createBillable();
    Spike::resolve(fn() => $fakeUser);
});

test('Credits::currentSubscriptionTransaction() returns the current subscription transaction', function () {
    expect(Credits::currentSubscriptionTransaction())->toBeNull()
        ->and(Credits::type('sms')->currentSubscriptionTransaction())->toBeNull();

    CreditTransaction::factory()->subscription()->create(['credits' => 100]);
    CreditTransaction::factory()->subscription()->type('sms')->create(['credits' => 50]);

    expect(Credits::currentSubscriptionTransaction())->not->toBeNull()
        ->credits->toBe(100)
        ->and(Credits::type('sms')->currentSubscriptionTransaction())->not->toBeNull()
        ->credits->toBe(50);
});
