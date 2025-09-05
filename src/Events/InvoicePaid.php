<?php

namespace Opcodes\Spike\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Opcodes\Spike\Contracts\SpikeBillable;
use Opcodes\Spike\SpikeInvoice;

class InvoicePaid
{
    use Dispatchable;

    /**
     * @param SpikeBillable $billable
     * @param SpikeInvoice $invoice
     */
    public function __construct(
        public mixed $billable,
        public SpikeInvoice $invoice,
    ) {}
}
