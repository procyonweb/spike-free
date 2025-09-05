<?php

namespace Opcodes\Spike;

use Carbon\CarbonInterface;

class SpikeInvoice
{
    public function __construct(
        public string $id,
        public string $number,
        public CarbonInterface $date,
        public ?string $status,
        public string $total,
    )
    {
    }
}
