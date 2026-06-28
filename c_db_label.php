<?php
// Copyright 2005-2026 Sergey Shustov.
// License: GPL v2.  See License.txt.

/** Provides optional alias to entity name, for presentation purposes */
class CDbLabel
{
    protected $s = array();
    
    /** The constructor */
    public function __construct($label, $plural=null)
    {
        $label = ucwords($label);
        if (!$plural) $plural = "{$label}s";
        
        // Camelcase
        $this->s[0] = $label;
        $this->s[1] = $plural;
        
        // Uppercase
        $this->s[2] = strtoupper($label);
        $this->s[3] = strtoupper($plural);
        
        // Lowercase
        $this->s[4] = strtolower($label);
        $this->s[5] = strtolower($plural);
    }
    
    /** Returns camelcase label */
    public function camel($plural=null) { return $plural ? $this->s[1] : $this->s[0]; }
    
    /** Returns uppercase label */
    public function upper($plural=null) { return $plural ? $this->s[3] : $this->s[2]; }
    
    /** Returns lowercase label */
    public function lower($plural=null) { return $plural ? $this->s[5] : $this->s[4]; }
    
    /** Returns camelcase */
    public function __toString() { return $this->s[0]; }
}
?>