<?php
// Copyright 2005-2026 Sergey Shustov.
// License: GPL v2.  See License.txt.

class CSingleton
{
    /** Don't allow cloning */
    final private function __clone() {}
    
    /** Get the instance of the class */
    public static function Instance()
    {
        static $instance = null;
        if (!$instance) $instance = new static();
        return $instance;
    }
}
?>