<?php

namespace Opcodes\Spike;

use Carbon\CarbonInterface;

class ProductPurchase
{
    public function __construct(
        public Product $product,
        public int $quantity,
        public CarbonInterface $purchased_at,
    )
    {
    }
}
