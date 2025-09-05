<?php

namespace Opcodes\Spike;

use Carbon\CarbonInterface;

class GroupedProductPurchase
{
    public function __construct(
        public Product $product,
        public int $quantity,
        public CarbonInterface $first_purchase_at,
        public CarbonInterface $last_purchase_at,
    )
    {
    }
}
