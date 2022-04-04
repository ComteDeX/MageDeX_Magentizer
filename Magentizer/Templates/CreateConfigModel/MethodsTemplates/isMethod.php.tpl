
    /**
    * @param int|null $scopeId
    * @return bool
    */
    public function is{{{config}}}(?int $scopeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::{{{config_const}}},
            ScopeInterface::SCOPE_STORE, $scopeId);
    }
