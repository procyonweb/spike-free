<?php

namespace Opcodes\Spike\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Opcodes\Spike\Product;
use Opcodes\Spike\Contracts\SpikeBillable;

class ProductPurchased
{
    use Dispatchable;

    /**
     * @param SpikeBillable $billable
     * @param Product $product
     * @param int $quantity
     */
    public function __construct(
        public mixed $billable,
        public Product $product,
        public int $quantity = 1,
    ) {}
}
