<?php

namespace Opcodes\Spike;

class CreditBalance
{
    private CreditType $type;
    private int $balance;

    public function __construct(
        CreditType|string $type,
        int $balance
    )
    {
        $this->type = CreditType::make($type);
        $this->balance = $balance;
    }

    public function balance(): int
    {
        return $this->balance;
    }

    public function type(): CreditType
    {
        return $this->type;
    }
}
