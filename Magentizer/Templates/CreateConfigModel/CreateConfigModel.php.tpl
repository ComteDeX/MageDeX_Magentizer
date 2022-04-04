<?php

declare (strict_types=1);

namespace {{{vendor}}}\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    {{{properties}}}
    private ScopeConfigInterface $scopeConfig;

    public function __construct(
    ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    {{{methods}}}
}
