<?php

namespace Opcodes\Spike\Traits;

use Illuminate\Database\Eloquent\Model;
use Opcodes\Spike\Actions\VerifyBillableUsesTrait;
use Opcodes\Spike\Contracts\SpikeBillable;
use Opcodes\Spike\Facades\Spike;

trait ScopedToBillable
{
    /** @var SpikeBillable|Model|null */
    protected $billable = null;

    /**
     * @param SpikeBillable|Model|null $billable
     * @return $this
     */
    public function billable($billable = null): static
    {
        $self = new static;
        $self->billable = $billable;

        return $self;
    }

    /**
     * @return SpikeBillable|Model|null
     * @throws \Exception
     */
    public function getBillable()
    {
        $billable = $this->billable ?? Spike::resolve();

        if (!is_null($billable)) {
            app(VerifyBillableUsesTrait::class)->handle($billable);
        }

        return $billable;
    }
}
