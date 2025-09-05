<?php

namespace Opcodes\Spike\Tests\Fixtures\Stripe;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Opcodes\Spike\Stripe\SpikeBillable;
use Orchestra\Testbench\Factories\UserFactory;

class Team extends AuthenticatableUser
{
    use HasFactory;
    use SpikeBillable;

    protected static function newFactory()
    {
        return new UserFactory();
    }
}
