<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Opcodes\Spike\CreditTransaction;
use Opcodes\Spike\Facades\Credits;
use Opcodes\Spike\Facades\Spike;
use function Spatie\PestPluginTestTime\testTime;

uses(RefreshDatabase::class);

beforeEach(function () {
    // We resolve the billable into a fake user. It's ok, because we don't call any methods on the actual user.
    $fakeUser = createBillable();
    Spike::resolve(fn() => $fakeUser);
});

test('Credits::currentUsageTransaction() returns the current usage credit transaction', function () {
    CreditTransaction::factory()->create(['credits' => 1000]);
    Credits::spend(50);

    expect(Credits::currentUsageTransaction())->not->toBeNull()->credits->toBe(-50)
        ->and(Credits::type('sms')->currentUsageTransaction())->not->toBeNull()->credits->toBe(0);
});

test('Credits::currentUsageTransaction() returns the latest usage transaction when there\'s multiple on the same day', function () {
    CreditTransaction::factory()->create(['credits' => 1000]);
    CreditTransaction::factory()->usage()->create(['credits' => -50, 'expires_at' => now()]);
    CreditTransaction::factory()->usage()->create(['credits' => -100]);

    $usageTransaction = Credits::currentUsageTransaction();

    expect($usageTransaction)->not->toBeNull()
        ->and($usageTransaction->credits)->toBe(-100);
});

test('Credits::currentUsageTransaction() returns a new (unsaved) usage transaction if there isn\'t one for today', function () {
    $usageTransaction = Credits::currentUsageTransaction();

    expect($usageTransaction)->not->toBeNull()
        ->and($usageTransaction->exists)->toBeFalse()
        ->and($usageTransaction->credits)->toBe(0);

    $smsTransaction = Credits::type('sms')->currentUsageTransaction();

    expect($smsTransaction)->not->toBeNull()
        ->and($smsTransaction->exists)->toBeFalse()
        ->and($smsTransaction->credits)->toBe(0);
});

test('Credits::currentUsageTransaction() resets when previous usage transaction expires', function () {
    // when a usage transaction is closed, it will mark itself as expired and any further usage will be
    // recorded on a new transaction. This can be used before certain tokens expire to help with calculation.

    CreditTransaction::factory()->create(['credits' => 1000]);
    CreditTransaction::factory()->usage()->create(['credits' => -50]);
    $transaction = Credits::currentUsageTransaction();
    expect($transaction->credits)->toBe(-50);
    testTime()->freeze();

    $transaction->expire();

    expect($transaction->expires_at->timestamp)->toBe(now()->timestamp)
        ->and(Credits::currentUsageTransaction())->not->toBeNull()
        ->credits->toBe(0)
        ->exists->toBeFalse();
});
