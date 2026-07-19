<?php
// Copyright 2005-2026 Sergey Shustov.
// License: GPL v2.  See License.txt.

/** Db Record Manager Singleton Class */
abstract class CDbRecordManSingleton extends CDbRecordMan
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
    
    /** The constructor */
    protected function __construct($table_name, $class_name, $filter = null, $sort_by = null)
    {
    	parent::__construct($table_name, $class_name, $filter, $sort_by);
    }
}
?>