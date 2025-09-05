<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Opcodes\Spike\Facades\Spike;
use Opcodes\Spike\SubscriptionPlan;

uses(RefreshDatabase::class);

it('defaults to free subscription', function () {
    $user = createBillable();
    config(['spike.subscriptions' => [
        $standardConfig = [
            'id' => 'standard',
            'name' => 'Standard',
            'short_description' => 'For small businesses with continuous use',
            'payment_provider_price_id_monthly' => 'standard_price_id_monthly',
            'price_in_cents_monthly' => 10_00,
            'provides_monthly' => [
                \Opcodes\Spike\CreditAmount::make(20_000),
            ],
        ],
        $freeConfig = [
            'id' => 'free',
            'name' => 'Different name',
            'short_description' => 'For small businesses with continuous use',
            'provides_monthly' => [
                $freeCredits = \Opcodes\Spike\CreditAmount::make(6_000),
            ],
        ],
    ]]);

    $currentPlan = $user->currentSubscriptionPlan();

    expect($currentPlan)->toBeInstanceOf(SubscriptionPlan::class)
        ->id->toBe($freeConfig['id'])
        ->name->toBe($freeConfig['name'])
        ->provides_monthly->toBe([$freeCredits]);
});
