<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Opcodes\Spike\Actions\ChargeForNegativeBalances;
use Opcodes\Spike\Facades\Credits;
use Opcodes\Spike\PaymentProvider;
use Opcodes\Spike\SpikeServiceProvider;

uses(RefreshDatabase::class);

beforeAll(function () {
    \Opcodes\Spike\SpikeServiceProvider::$_cached_payment_provider = \Opcodes\Spike\PaymentProvider::Paddle;
});
afterAll(function () {
    \Opcodes\Spike\SpikeServiceProvider::$_cached_payment_provider = null;
});

beforeEach(function () {
    Credits::allowNegativeBalance();
    config(['spike.credit_types' => [
        [
            'id' => 'credits',
            'payment_provider_price_id' => 'price_single_credit',
        ],
        [
            'id' => 'sms',
            'payment_provider_price_id' => 'price_single_sms',
        ],
    ]]);
});

it('does nothing using Paddle payment provider', function () {
    $oldProvider = SpikeServiceProvider::$_cached_payment_provider;

    try {
        SpikeServiceProvider::$_cached_payment_provider = PaymentProvider::Paddle;
        \Illuminate\Support\Facades\Log::shouldReceive('warning')->with(
            "Billable has negative credit balances, but payment provider does not support offline charges. Credits will not be charged for.",
            Mockery::type('array')
        );
        $billable = createBillable();
        $billable->credits()->spend(10);

        $action = new ChargeForNegativeBalances();
        $action->handle($billable);

        expect($billable->credits()->balance())->toBe(-10);
    } finally {
        SpikeServiceProvider::$_cached_payment_provider = $oldProvider;
    }
});
