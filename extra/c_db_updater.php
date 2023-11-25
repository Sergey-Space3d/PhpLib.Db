<?php
// Copyright 2005-2022 Sergey Shustov.
// License: GPL v2.  See License.txt.

/** Updates db tables (data types only) */
class CDbUpdater
{
    protected $table = null;
    protected $db_fields = array();
    protected $rec_fields = array();
    
    /** The constructor */
    public function __construct(CDbRecord $record)
    {
        $this->table = $record->get_table();

        foreach ($this->table->get_columns() as $col)
        {
            $this->db_fields[$col['Field']] = $this->normalize_db_field($col);
        }
        
        foreach ($record->get_field_def() as $field=>$type)
        {
            $this->rec_fields[$field] = $this->normalize_rec_field($type);
        }
    }
    
    /** Normalize db field */
    protected function normalize_db_field($col)
    {
        $type = strtolower($col['Type']);
        if (substr($type, 0, 3) == 'int') $type = 'int';
        else if (in_array($type, array('tinyint(1)', 'bool', 'boolean'))) $type = 'bool';
        
        if ($col['Extra'] == 'auto_increment') $type .= ' AUTO_INCREMENT';
        else if ($col['Key'] == 'PRI') $type .= ' PRIMARY KEY';
        else if (in_array($col['Key'], array('MUL', 'UNI'))) $type .= ' UNIQUE';
        
        if ($col['Null'] != 'YES') $type .= ' NOT NULL';
        
        return $type;
    }
    
    /** Normalize record field */
    protected function normalize_rec_field($type)
    {
        $arr = explode(' ', $type, 2);
        
        $type = strtolower($arr[0]);
        if (substr($type, 0, 3) == 'int') $type = 'int';
        else if (in_array($type, array('tinyint(1)', 'bool', 'boolean'))) $type = 'bool';
        
        if ($arr[1])
        {
            if (stristr($arr[1], 'AUTO_INCREMENT')) $type .= ' AUTO_INCREMENT';
            else if (stristr($arr[1], 'PRIMARY KEY')) $type .= ' PRIMARY KEY';
            else if (stristr($arr[1], 'UNIQUE')) $type .= ' UNIQUE';
            
            if (stristr($arr[1], 'NOT NULL')) $type .= ' NOT NULL';
        }
        
        return $type;
    }
    
    /** Returns true if the data types are identical */
    protected function are_types_identical($db_type, $rec_type)
    {
        return $db_type == $rec_type;
    }
    
    /** Returns true if the field is base field */
    protected function is_base_field($field)
    {
        return $field == 'id' || $field == 'ts' || $field == 'flags';
    }
    
    /** Get updated fields */
    public function get_updated_fields()
    {
        $ret = array();
        
        foreach ($this->db_fields as $field=>$type)
        {
            if (isset($this->rec_fields[$field]) && !$this->are_types_identical($type, $this->rec_fields[$field]))
            {
                $ret[$field] = $this->rec_fields[$field];
            }
        }
        
        return $ret;
    }
    
    /** Get deleted fields */
    public function get_deleted_fields()
    {
        $ret = array();
        
        foreach ($this->db_fields as $field=>$type)
        {
            if (!isset($this->rec_fields[$field]) && !$this->is_base_field($field))
            {
                $ret[$field] = $type;
            }
        }
        
        return $ret;
    }
    
    /** Get added fields */
    public function get_added_fields()
    {
        $ret = array();
        
        foreach ($this->rec_fields as $field=>$type)
        {
            if (!isset($this->db_fields[$field]) && !$this->is_base_field($field))
            {
                $ret[$field] = $type;
            }
        }
        
        return $ret;
    }
    
    /** Update Db table's layout */
    public function update()
    {
        $tname = CDbTable::make_name($this->table->get_db_name(), $this->table->get_name());
        $fields = $this->get_updated_fields();
        
        foreach ($fields as $field=>$type)
        {
            $statement = "ALTER TABLE {$tname} MODIFY COLUMN {$field} {$type}";
            $this->table->query($statement);
        }
        
        $fields = $this->get_deleted_fields();
        
        foreach ($fields as $field=>$type)
        {
            $statement = "ALTER TABLE {$tname} DROP COLUMN {$field}";
            $this->table->query($statement);
        }
        
        $fields = $this->get_added_fields();
        
        foreach ($fields as $field=>$type)
        {
            $statement = "ALTER TABLE {$tname} ADD COLUMN {$field} {$type}";
            $this->table->query($statement);
        }
        
        return true;
    }
}
?>