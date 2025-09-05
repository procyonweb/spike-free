<?php

namespace Opcodes\Spike\Traits;

use Carbon\CarbonInterval;

trait ExpirableMethods
{
    public function expiresAfter(?CarbonInterval $expires_after = null): self
    {
        $this->expires_after = $expires_after;

        return $this;
    }

    public function getExpiresAfter(): ?CarbonInterval
    {
        return $this->expires_after;
    }

    public function doesNotExpire(): self
    {
        $this->expires_after = null;

        return $this;
    }

    public function expiresAfterDays(int $days): self
    {
        return $this->expiresAfter(CarbonInterval::days($days));
    }

    public function expiresAfterWeeks(int $weeks): self
    {
        return $this->expiresAfter(CarbonInterval::weeks($weeks));
    }

    public function expiresAfterMonths(int $months): self
    {
        return $this->expiresAfter(CarbonInterval::months($months));
    }

    public function expiresAfterYears(int $years): self
    {
        return $this->expiresAfter(CarbonInterval::years($years));
    }
}
