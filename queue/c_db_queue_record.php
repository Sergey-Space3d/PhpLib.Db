<?php
// Copyright 2005-2026 Sergey Shustov.
// License: GPL v2.  See License.txt.

/** Queue Record Class */
abstract class CDbQueueRecord extends CDbRecord
{
    /** The constructor */
    public function __construct($table_name)
    {
        parent::__construct($table_name, 0);
    }
    
    /** Returns field definitions for the table */
    public function get_field_def()
    {
        return array('contents'=>'MEDIUMBLOB');
    }
    
    /** Called when writing new record to the database */
    protected function on_write_new()
    {
        $this->m_fields['contents'] = serialize($this);
    }
}
?>