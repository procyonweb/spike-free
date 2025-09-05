<?php

namespace Opcodes\Spike\Contracts;

use Carbon\CarbonInterval;

interface Expirable
{
    public function expiresAfter(?CarbonInterval $expires_after = null): self;

    public function getExpiresAfter(): ?CarbonInterval;
}
