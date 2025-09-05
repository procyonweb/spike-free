<?php

use Opcodes\Spike\Facades\Spike;
use Opcodes\Spike\Tests\Fixtures\Stripe\Team;
use Opcodes\Spike\Tests\Fixtures\Stripe\User;

it('can resolve default billable', function () {
    $user = new User();
    Spike::resolve(fn ($request) => $user);

    expect(Spike::resolve())->toBe($user);
});

it('can resolve a billable for a different model', function () {
    $team = new Team();
    Spike::billable(Team::class)->resolve(fn ($request) => $team);
    config(['spike.billable_models' => [Team::class]]);

    expect(Spike::resolve())->toBe($team);
});
