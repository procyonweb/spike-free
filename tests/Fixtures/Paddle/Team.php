<?php

namespace Opcodes\Spike\Tests\Fixtures\Paddle;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Opcodes\Spike\Paddle\SpikeBillable;
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
