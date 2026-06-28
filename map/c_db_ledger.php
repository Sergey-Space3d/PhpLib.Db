<?php
// Copyright 2005-2026 Sergey Shustov.
// License: GPL v2.  See License.txt.

/** Database ledger (maps items to named container, or ledger) */
class CDbLedger extends CDbRecord
{
    private static $m_lfd = array('_name'=>'varchar(64)', '_desc'=>'varchar(255)');
    
    /** Returns field definitions for the table */
    public function get_field_def()
    {
        return self::$m_lfd;
    }
    
    /** Add fields to the database. Returns primary key (id) */
    public function write($field = null, $force_id = false)
    {
        if ($this->m_table->has($this->get_duplicate_condition())) return 0;
        return parent::write($field, $force_id);
    }
    
    /** Called when writing new record to the database */
    protected function on_write_new() {}
    
    /** Returns true if the record is valid */
    public function verify()
    {
        return strlen($this->m_fields['_name']) > 1;
    }
    
    /** Returns duplicate condition */
    public function get_duplicate_condition()
    {
        $name = strtolower($this->m_fields['_name']);
        $condition = "lower(_name)='{$name}'";
        if ($this->m_fields['id']) $condition .= " AND id!={$this->m_fields['id']}";
        return $condition;
    }
    
    public function get_name() { return $this->m_fields['_name']; }
    public function get_desc() { return $this->m_fields['_desc']; }
    
    public function set_name($arg, $save = false) { $this->set_field('_name', $arg, $save); }
    public function set_desc($arg, $save = false) { $this->set_field('_desc', $arg, $save); }
}
?>