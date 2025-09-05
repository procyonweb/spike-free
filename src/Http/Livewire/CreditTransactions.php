<?php

namespace Opcodes\Spike\Http\Livewire;

use Opcodes\Spike\CreditTransaction;
use Opcodes\Spike\Facades\Spike;
use Livewire\Component;
use Livewire\WithPagination;

class CreditTransactions extends Component
{
    use WithPagination;

    public $loadTransactions = true;
    public $sortBy = 'created_at';
    public $sortOrder = 'desc';

    public function render()
    {
        $billable = Spike::resolve();

        if ($this->loadTransactions) {
            $creditTransactions = CreditTransaction::whereBillable($billable)
                ->orderBy($this->sortBy, $this->sortOrder)
                ->orderBy('id', $this->sortOrder)
                ->paginate(10);
        }

        return view('spike::livewire.credit-transactions', [
            'transactions' => $creditTransactions ?? collect([]),
        ]);
    }
}
