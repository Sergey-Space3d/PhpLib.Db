<?php
// Copyright 2005-2026 Sergey Shustov.
// License: GPL v2.  See License.txt.

/** Defines mapped ledger */
class CDbMappedLedger extends CDbRecord
{
    private static $m_mlfd = array('_name'=>'varchar(32) UNIQUE', '_map'=>'text');

    /** Returns field definitions for the table */
    public function get_field_def()
    {
        return self::$m_mlfd;
    }
    
    /** Called when writing new record to the database */
    protected function on_write_new() {}
    
    /** Returns true if the record is valid */
    public function verify()
    {
        return $this->m_fields['_name'] && $this->m_fields['_map'];
    }
    
    public function get_name() { return $this->m_fields['_name']; }
    public function set_name($arg, $save = false) { $this->set_field('_name', $arg, $save); }
    
    /** Get map as array */
    public function get_map()
    {
        return $this->m_fields['_map'] ? explode(',', $this->m_fields['_map']) : array();
    }
    
    /** Set map as array */
    public function set_map(array $arg, $save = false)
    {
        $arg = implode(',', $arg);
        $this->set_field('_map', $arg, $save);
    }
}
?>