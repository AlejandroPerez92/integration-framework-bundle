<?php

namespace Smartbox\Integration\FrameworkBundle\Traits;

trait FlowsVersionAware
{
    protected $flowsVersion;

    /**
     * @return string
     */
    public function getFlowsVersion()
    {
        return (string) $this->flowsVersion;
    }

    /**
     * @param mixed $flowsVersion
     */
    public function setFlowsVersion($flowsVersion)
    {
        $this->flowsVersion = $flowsVersion;
    }
}