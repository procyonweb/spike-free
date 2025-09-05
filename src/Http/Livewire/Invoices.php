<?php

namespace Opcodes\Spike\Http\Livewire;

use Opcodes\Spike\Facades\Spike;
use Livewire\Component;

class Invoices extends Component
{
    public bool $shouldLoadInvoices = false;

    public function loadInvoices()
    {
        $this->shouldLoadInvoices = true;
    }

    public function render()
    {
        $billable = Spike::resolve();

        return view('spike::livewire.invoices', [
            'invoices' => $this->shouldLoadInvoices ? $billable->spikeInvoices() : [],
        ]);
    }
}
