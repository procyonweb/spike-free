<?php

namespace Opcodes\Spike;

class SpikeManagerFake extends SpikeManager
{
    protected ?array $_customSubscriptionPlans;
    protected ?array $_customProducts;

    public function products($billableInstance = null, bool $includeArchived = false)
    {
        if (!empty($this->_customProducts)) {
            return $this->_customProducts;
        }

        return parent::products($billableInstance, $includeArchived);
    }

    public function setProducts($products): void
    {
        $this->_customProducts = $products;
    }

    public function subscriptionPlans($billableInstance = null, bool $includeArchived = false)
    {
        if (!empty($this->_customSubscriptionPlans)) {
            return $this->_customSubscriptionPlans;
        }

        return parent::subscriptionPlans($billableInstance);
    }

    public function setSubscriptionPlans($plans): void
    {
        $this->_customSubscriptionPlans = $plans;
    }
}
