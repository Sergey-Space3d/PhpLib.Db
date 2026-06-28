<?php
// Copyright 2005-2026 Sergey Shustov.
// License: GPL v2.  See License.txt.

/** Db Record Manager Class (optional filter is applied) */
abstract class CDbRecordMan
{
	protected $m_table = null;
	protected $m_table_name = null;
	protected $m_class_name = null;
	protected $m_filter = null;
	protected $m_sort_by = null;
	protected $m_dependents = null;
	protected $m_references = null;
    protected $m_ondelete = null;
    
    // Setup/cleanup --------------------------------------------------------
    
	/** The constructor */
    protected function __construct($table_name, $class_name, $filter = null, $sort_by = null)
	{
	    $this->m_table = self::create_table($table_name, $class_name);
		$this->m_class_name = $class_name;
		$this->m_table_name = $table_name;
		$this->m_filter = $filter;
		$this->m_sort_by = $sort_by;
		$this->init_dependencies();
	}
	
	/** Initialize db dependencies */
	protected function init_dependencies() {}
	
	/** Returns table name */
	public function get_table_name() { $this->m_table_name; }
	
	/** Create table, if doesn't exist */
	public static function create_table($table_name, $class_name)
	{
	    $obj = new $class_name();
	    return CDbTable::create_table($table_name, $obj->get_field_def());
	}
	
	/** Add id reference to the table records (by containtment, or reference) */
	public function add_id_reference($table_name, $field_name, $containtment = true)
	{
	    if ($containtment)
	    {
    	    if (!$this->m_dependents) $this->m_dependents = array();
    	    $this->m_dependents[$table_name] = $field_name;
	    }
	    else 
	    {
	        if (!$this->m_references) $this->m_references = array();
	        $this->m_references[$table_name] = $field_name;
	    }
	}
	
	/** Add on delete callback */
	public function add_on_delete_callback($fn)
	{
	    if (is_callable($fn))
	    {
    	    if (!$this->m_ondelete) $this->m_ondelete = array();
    	    $this->m_ondelete[] = $fn;
	    }
	}
	
	/** Delete record */
	public function delete($id)
	{
	    if ($this->m_ondelete)
	    {
	        foreach ($this->m_ondelete as $fn)
	        {
	            try 
	            {
	                $fn($id);
	            } 
	            catch(Exception $e) {}
	        }
	    }
	    
	    if ($this->m_dependents)
	    {
	        foreach ($this->m_dependents as $tname=>$fname)
    	    {
    	        try
    	        {
    	            $table = new CDbTable($tname);
    	            $table->delete("{$fname}={$id}");
    	        } 
    	        catch(Exception $e) {}
    	    }
	    }
	    
	    if ($this->m_references)
	    {
	        foreach ($this->m_references as $tname=>$fname)
	        {
	            try
	            {
	                $table = new CDbTable($tname);
	                $result = $table->get("{$fname}={$id}", null, true);
	                
	                if ($result)
	                {
	                    foreach ($result as $row)
	                    {
	                        $table->update($row['id'], array($fname=>0));
	                    }
	                }
	            } 
	            catch(Exception $e) {}
	        }
	    }
	    
	    return $this->m_table->delete($id);
	}
	
	/** Delete all records */
	public function delete_all()
	{
		// Get all the ids
		$count = 0;
		$result = $this->m_table->get_fields($this->m_filter, null, 'id');

		if ($result)
		{
			foreach ($result as $row)
			{
				if ($this->delete($row['id']))
					$count++; // increment the counter
			}
		}

		return $count;
	}
	
	/** Remove old or unused records. Returns number of removed items */
	public function cleanup($ts_offset = 0, $capacity = 0)
	{
	    $n = 0;
	    $result = null;
	    
	    if ($capacity > 0)
	    {
	        $num_records = $this->m_table->get_num_records();
	        
	        if ($num_records > $capacity)
	        {
	            $result = $this->m_table->get(null, 'ts ASC', true, $num_records - $capacity);
	        }
	    }
	    
	    if (!$result && $ts_offset > 0)
	    {
	        $now = time();
	        $condition = "({$now}-ts)>{$ts_offset}";
	        $result = $this->m_table->get($condition, null, true);
	    }
	    
	    if ($result)
	    {
	        $n = count($result);
	        
	        foreach ($result as $row)
	        {
	            $this->delete($row['id']);
	        }
	    }
	    
	    return $n;
	}
	
	// Filters --------------------------------------------------------------
	
	/** Set filter (null resets the filter). Returns previous filter */
	public function set_filter($filter) 
	{ 
	    $ret = $this->m_filter;
	    
	    if (!$filter)
	    {
	        $this->m_filter = null;
	    }
	    else if (is_array($filter))
	    {
	        foreach ($filter as &$f) $f = "({$f})";
	        $this->m_filter = implode(' AND ', $filter);
	    }
	    else 
	    {
	        $this->m_filter = $filter; 
	    }
	    
	    return $ret;
	}
	
	/** Returns filtered condition */
	public function merge_filter($condition)
	{
	    return $this->m_filter ? "({$this->m_filter}) AND ({$condition})" : $condition;
	}
	
	/** Returns timestamp filter (the range is inclusive) */
	public function make_ts_filter($ts_from, $ts_to) { return "ts>={$ts_from} AND ts<={$ts_to}"; }
	
	/** Set order (null resets the order). Returns previous order */
	public function set_order($sort_by)
	{
	    $ret = $this->m_sort_by;
	    
	    if (!$sort_by) $this->m_sort_by = 'id ASC';
	    else if (is_array($sort_by)) $this->m_sort_by = implode(',', $sort_by);
	    else $this->m_sort_by = $sort_by;
	    
	    return $ret;
	}
	
	/** Returns order string */
	public function get_order() { return $this->m_sort_by ? $this->m_sort_by : 'id ASC'; }
	
	/** Make "order" argument for CDbTable::get() */
	protected function make_order($sort_by)
	{
	    if (!$sort_by)
	    {
	        if ($this->m_sort_by) return $this->m_sort_by; // Ignore asc
	        $sort_by = 'id ASC'; // Make id default
	    }
	    
	    return $sort_by; 
	}
	
	// Getters --------------------------------------------------------------
	
	/** Get table */
	public function get_table() { return $this->m_table; }

	/** Get array of instances, or ids */
	public function get($sort_by = null, $return_instances = true)
	{
	    $result = $this->m_table->get($this->m_filter, $this->make_order($sort_by), !$return_instances);
	    return $return_instances ? $this->to_instances($result) : $this->to_ids($result);
	}

	/** Get by flags. Returns array of instances */
	public function get_by_flags($yes_flags, $no_flags = 0, $sort_by = null, $return_instances = true)
	{
		$records = array();
		$result = $this->m_table->get($this->m_filter, $this->make_order($sort_by));

		if ($result)
		{
			foreach ($result as $row)
			{
				if ((((int)$row['flags'] & (int)$yes_flags) == (int)$yes_flags) &&
				    !((int)$row['flags'] & (int)$no_flags))
				{
	                $records[] = $row;
	            }
			}
		}

		return $return_instances ? $this->to_instances($records) : $this->to_ids($records);
	}

	// Instances ------------------------------------------------------------
	
	/** Get instance by condition */
	public function get_instance($condition, $sort_by = null)
	{
	    $arr = $this->get_instances($condition, $sort_by);
	    return array_shift($arr);
	}
	
	/** Get instances by condition */
	public function get_instances($condition, $sort_by = null)
	{
	    $result = $this->m_table->get($this->merge_filter($condition), $this->make_order($sort_by));
	    return $this->to_instances($result);
	}
	
	/** Returns instance of the class. Override if the constructor arguments are required */
	protected function make_instance()
	{
	    return new $this->m_class_name;
	}
	
	/** Convert array of fields to instance */
	protected function to_instance(array $row)
	{
	    $record = $this->make_instance();
	    $record->set($row);
	    return $record;
	}
	
	/** Convert an array of fields to instances */
	protected function to_instances($result)
	{
		$records = array();
		
		if ($result)
		{
			foreach ($result as $row)
			{
			    $record = $this->make_instance();
				$record->set($row);
				$id = $row['id'];
				$records[$id] = $record;
			}
		}
		
		return $records;
	}
	
	/** Returns numeric id */
	protected function to_id($id)
	{
	    return ($id instanceof CDbRecord) ? $id->get_id() : (int)$id;
	}
	
	/** Convert an array of fields to ids */
	protected function to_ids($result)
	{
		$records = array();
		
		if ($result)
		{
			foreach ($result as $row)
			{
			    $id = $row['id'];
			    $records[$id] = $id;
			}
		}
		
		return $records;
	}
	
	/** Convert argument to array of ids */
	protected function to_id_array($arg)
	{
	    $ret = is_array($arg) ? $arg : array($arg);
	    
	    foreach ($ret as &$val)
	    {
	        if ($val instanceof CDbRecord)
	        {
	            $val = $val->get_id();
	        }
	    }
	    
	    return $ret;
	}
}
?>