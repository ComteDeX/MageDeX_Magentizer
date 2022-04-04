
    /**
    * @param int|null $scopeId
    * @return string
    */
    public function get{{{config}}}(?int $scopeId = null): string
    {
        return $this->scopeConfig->getValue(self::{{{config_const}}},
            ScopeInterface::SCOPE_STORE, $scopeId)  ?? '';
    }
