<?php

use Opcodes\Spike\CreditManager;

test('ManagesCredits trait provides access to the credits manager', function () {
    $user = createBillable();
    $secondUser = createBillable();

    $firstManager = $user->credits();
    $secondManager = $secondUser->credits();

    expect($firstManager)->toBeInstanceOf(CreditManager::class)
        ->and($firstManager->getBillable())->toBe($user)
        ->and($secondManager)->toBeInstanceOf(CreditManager::class)
        ->and($secondManager->getBillable())->toBe($secondUser);
});

test('can provide the type of credits to manage', function () {
    config(['spike.credit_types' => [
        ['id' => 'credits'],
        ['id' => 'sms'],
    ]]);
    $user = createBillable();

    expect($user->credits()->getCreditType()->type)->toBe('credits')
        ->and($user->credits('sms')->getCreditType()->type)->toBe('sms');
});
