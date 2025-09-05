<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Opcodes\Spike\CreditAmount;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['spike.subscriptions' => [
        [
            'id' => 'free',
            'name' => 'Free',
            'provides_monthly' => [
                CreditAmount::make($this->credits = 10)
            ],
        ],
    ]]);
});

function fireModelEvent($model, $event)
{
    // using reflection, make `fireModelEvent` on $model public
    $method = new \ReflectionMethod($model, 'fireModelEvent');
    /** @noinspection PhpExpressionResultUnusedInspection */
    $method->setAccessible(true);
    // call the method
    $method->invoke($model, $event);
}

it('provides the credits from the default free plan to a new registered user', function () {
    $user = createBillable();

    fireModelEvent($user, 'created');

    expect($user->credits()->balance())->toBe($this->credits);
});

it('does not create a transaction if theres no monthly credits to give', function () {
    config(['spike.subscriptions' => [
        [
            'id' => 'free',
            'name' => 'Free',
            'monthly_credits' => 0,
        ],
    ]]);
    $user = createBillable();

    fireModelEvent($user, 'created');

    expect($user->credits()->balance())->toBe(0)
        ->and(\Opcodes\Spike\CreditTransaction::first())->toBeNull();
});

it('does not apply credits twice', function () {
    $user = createBillable();
    fireModelEvent($user, 'created');
    expect($user->credits()->balance())->toBe($this->credits);

    fireModelEvent($user, 'created');

    expect($user->credits()->balance())->toBe($this->credits);
});

it('does nothing to a non-Spike billable user', function () {
    $otherUser = new class extends \Illuminate\Database\Eloquent\Model implements \Illuminate\Contracts\Auth\Authenticatable {
        use \Illuminate\Auth\Authenticatable;
    };

    try {
        fireModelEvent($otherUser, 'created');
        $this->assertFalse(method_exists($otherUser, 'credits'));
    } catch (\Exception $e) {
        $this->fail('An exception was thrown: ' . $e->getMessage());
    }
});
