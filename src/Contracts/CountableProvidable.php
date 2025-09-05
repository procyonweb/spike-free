<?php

namespace Opcodes\Spike\Contracts;

interface CountableProvidable extends Providable
{
    public function setAmount(int $amount): CountableProvidable;

    public function getAmount(): int;
}
