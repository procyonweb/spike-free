<?php

use Opcodes\Spike\CreditType;
use Opcodes\Spike\Facades\Credits;

beforeEach(function () {
    config(['spike.credit_types' => [
        [
            'id' => 'credits',
            'translation_key' => 'credit|credits',
            'icon' => null,
            'allow_negative_balance' => false,
        ],
    ]]);
});

it('can transform to array', function () {
    $type = CreditType::make('credits');

    expect($type->toArray())->toBe([
        'type' => 'credits',
        'name' => 'credits',
        'icon' => null,
    ]);
});

it('can check whether credit type is valid', function () {
    expect(CreditType::make('credits')->isValid())->toBeTrue();
    expect(CreditType::make('invalid')->isValid())->toBeFalse();
});

it('can check whether credit type is equal to another', function () {
    expect(CreditType::make('credits')->is('credits'))->toBeTrue();
    expect(CreditType::make('credits')->is('invalid'))->toBeFalse();
});
