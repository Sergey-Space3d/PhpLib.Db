<?php
// Copyright 2005-2026 Sergey Shustov.
// License: GPL v2.  See License.txt.

/** Database Map Manager Class */
class CDbMapMan extends CDbRecordMan
{
    /** The constructor */
    public function __construct($table_name, $class_name = 'CDbMap')
    {
        parent::__construct($table_name, $class_name);
    }
    
    /** Returns instance of the class */
    protected function make_instance()
    {
        return new $this->m_class_name($this->m_table_name);
    }
    
    /** Returns SQL condition */
    protected function make_condition($id1, $id2, $flags)
    {
        $id1 = $this->to_id($id1);
        $id2 = $this->to_id($id2);
        
        $arr = array();
        if ($id1) $arr[] = "id1={$id1}";
        if ($id2) $arr[] = "id2={$id2}";
        if ($flags) $arr[] = "flags={$flags}";
        
        return implode(' AND ', $arr);
    }
    
    /** Do actual mapping */
    protected function do_map($id1, $id2, $flags = null)
    {
        if ($id1 && $id2)
        {
            $condition = $this->make_condition($id1, $id2, $flags);
            
            if (!$this->m_table->has($condition))
            {
                // Record new mapping
                $map = $this->make_instance();
                $map->set_id1($id1);
                $map->set_id2($id2);
                if ($flags) $map->set_flags($flags);
                $map->write();
            }
        }
    }
    
    /** Map first item to second item (may be arrays of ids, or CDbRecord instances) */
    public function map($id1, $id2, $flags = null)
    {
        $ids1 = is_array($id1) ? $id1 : array($id1);
        $ids2 = is_array($id2) ? $id2 : array($id2);
        if ($flags) $flags = is_array($flags) ? $flags : array($flags);
        
        foreach ($ids1 as $id1)
        {
            if ($flags) reset($flags);
            $f = $flags ? current($flags) : null;
            
            foreach ($ids2 as $id2)
            {
                $id1 = $this->to_id($id1);
                $id2 = $this->to_id($id2);
                $this->do_map($id1, $id2, $f);
                
                $f = $flags ? next($flags) : null;
            }
        }
    }
    
    /** Unmap item(s). Note, that the first has to be id, instance, or null, the second may be an array */
    public function unmap($id1, $id2 = null, $flags = null)
    {
        $id1 = $this->to_id($id1);
        
        if ($id1 && $id2 === null)
        {
            $condition = $this->make_condition($id1, $id2, $flags);
            $this->m_table->delete($condition);
        }
        else if ($id2 !== null)
        {
            $ids2 = is_array($id2) ? $id2 : array($id2);
            
            foreach ($ids2 as $id2)
            {
                $condition = $this->make_condition($id1, $id2, $flags);
                $this->m_table->delete($condition);
            }
        }
    }
    
    /** Returns true if the mapping exists, false otherwise. The ids can be instances */
    public function has($id1, $id2, $flags = null)
    {
        if (!$id1 && !$id2) return false;
        
        $condition = $this->make_condition($id1, $id2, $flags);
        return $this->m_table->has($condition);
    }

    /** Returns map if exists, null otherwise. The ids can be instances */
    public function get_map($id1, $id2, $flags = null)
    {
        if (!$id1 && !$id2) return null;
        
        $condition = $this->make_condition($id1, $id2, $flags);
        $result = $this->m_table->get($condition, null, false, 1);
        return $result ? $this->to_instance(current($result)) : null;
    }
    
    /** Get map instances by first id. The id can be instance */
    public function get_by_id1($id1, $flags = null)
    {
        if (!$id1) return array();
        
        $condition = $this->make_condition($id1, 0, $flags);
        $result = $this->m_table->get($condition);
        return $this->to_instances($result);
    }
    
    /** Get map instances by second id. The id can be instance */
    public function get_by_id2($id2, $flags = null)
    {
        if (!$id2) return array();
        
        $condition = $this->make_condition(0, $id2, $flags);
        $result = $this->m_table->get($condition);
        return $this->to_instances($result);
    }
}
?>