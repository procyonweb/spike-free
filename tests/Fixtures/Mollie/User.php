<?php

namespace Opcodes\Spike\Tests\Fixtures\Mollie;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Opcodes\Spike\Mollie\SpikeBillable;

class User extends Authenticatable
{
    use HasFactory;
    use SpikeBillable;

    protected $guarded = [];

    protected $hidden = [
        'password',
        'remember_token',
    ];
}
