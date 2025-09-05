<?php

namespace Opcodes\Spike\Traits;

use Opcodes\Spike\CreditManager;

trait ManagesCredits
{
    public function creditManager(): CreditManager
    {
        return app(CreditManager::class)->billable($this);
    }

    public function credits(?string $type = null): CreditManager
    {
        if (isset($type)) {
            return $this->creditManager()->type($type);
        }

        return $this->creditManager();
    }
}
