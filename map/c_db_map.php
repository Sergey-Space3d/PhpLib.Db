<?php
// Copyright 2005-2026 Sergey Shustov.
// License: GPL v2.  See License.txt.

/** Database map, maps external primary keys */
class CDbMap extends CDbRecord
{
    private static $m_mfd = array('id1'=>'int', 'id2'=>'int');
    
    /** Returns field definitions for the table */
    public function get_field_def()
    {
        return self::$m_mfd;
    }
    
    /** Called when writing new record to the database */
    protected function on_write_new() {}
    
    /** Returns true if the record is valid */
    public function verify()
    {
        return $this->m_fields['id1'] != 0 && $this->m_fields['id2'] != 0;
    }

    public function get_id1() { return (int)$this->m_fields['id1']; }
    public function get_id2() { return (int)$this->m_fields['id2']; }
    
    public function set_id1($arg, $save = false) { $this->set_field('id1', (int)$arg, $save); }
    public function set_id2($arg, $save = false) { $this->set_field('id2', (int)$arg, $save); }
}
?>