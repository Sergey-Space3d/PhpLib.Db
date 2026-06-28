<?php
// Copyright 2005-2026 Sergey Shustov.
// License: GPL v2.  See License.txt.

/** Represents a record (row) of database table */
abstract class CDbRecord
{
	const HIDDEN	= 0x04000000;
	const ARCHIVED	= 0x08000000;
	const READ_ONLY	= 0x10000000;
	const DIRTY     = 0x20000000;
	
	protected $m_table = null;
	protected $m_fields = array();
	protected static $m_labels = array();

	/** The constructor */
	public function __construct($table_name = null, $id = 0)
	{
	    if ($table_name)
	    {
    		$this->m_table = CDbTable::create_table($table_name, $this->get_field_def());
    		$this->read($id);
	    }
	}
	
	/** Optional alias to entity */
	public static function label(CDbLabel $label = null)
	{
	    $key = get_class(new static());
	    if ($label) self::$m_labels[$key] = $label;
	    else if (!self::$m_labels[$key]) self::$m_labels[$key] = new CDbLabel($key);
	    return self::$m_labels[$key];
	}
	
	/** Returns true if both records are identical */
	public function equal_to(CDbRecord $r) 
	{ 
	    return 
	    $this->m_table->get_db_name() == $r->m_table->get_db_name() &&
	    $this->m_table->get_name() == $r->m_table->get_name() &&
	    $this->m_fields == $r->m_fields;
	}
	
	// Abstract functions ---------------------------------------------------

	public abstract function get_field_def();
	protected abstract function on_write_new();
	public abstract function verify();

	/** Get table */
	public function get_table()
	{
		return $this->m_table;
	}

	/** Set all fields */
	public final function set(array $fields, $save = false)
	{
		$this->m_fields = $fields;
		return $save ? $this->write(null, true) : true;
	}

	/** Get all fields */
	public final function get()
	{
		return $this->m_fields;
	}
	
	/** Set field's value. Returns true on success */
	protected final function set_field($field, $value, $save = false)
	{
	    $this->m_fields[$field] = $value;
	    return $save ? $this->write($field, true) : true;
	}
	
	/** Get field's value */
	protected final function get_field($field)
	{
	    return $this->m_fields[$field];
	}
	
	protected final function field_to_int($field) { $this->m_fields[$field] = (int)$this->m_fields[$field]; }
	protected final function field_to_float($field) { $this->m_fields[$field] = (float)$this->m_fields[$field]; }
	protected final function field_to_bool($field) { $this->m_fields[$field] = (int)$this->m_fields[$field]; }
	
	/** Delete record. Returns true on success */
	public function delete()
	{
		if (!$this->m_table || (int)$this->m_fields['id'] == 0)
			return 0; // invalid table or new record

		return $this->m_table->delete((int)$this->m_fields['id']) > 0;
	}
	
	/** Read fields from database. Returns true on success */
	public function read($id = null)
	{
		if ($id === null)
			$id = (int)$this->m_fields['id'];
			
		if ($id != 0)
		{
		    $result = $this->m_table->get("id={$id}", null, false, 1);
	
			if ($result)
			{
				$this->m_fields = $result[0];
				return true;
			}
		}
		
		return false;
	}

	/** Add fields to the database. Returns primary key (id) */
	public function write($field = null, $force_id = false)
	{
		if ($field)
		{
			if ((int)$this->m_fields['id'] == 0)
				return false; // no field update if new

			// Update the named field
			$arr = array();
			$arr[$field] = $this->m_fields[$field];
			return $this->m_table->update($this->m_fields['id'], $arr);
		}

		if ((int)$this->m_fields['id'] == 0)
		{
			// Chance to initialize fields before saving
			$this->on_write_new();

			if (!$this->verify()) // invalid data
			    return false;
			    
			// Set the timestamp
	        if ($this->m_fields['ts'] == 0)
	        	$this->m_fields['ts'] = time();

	        // Set flags to integer
	        if (!$this->m_fields['flags'])
	        	$this->m_fields['flags'] = 0;
	        	
			// Add new record
			$this->m_fields['id'] = (int)$this->m_table->add($this->m_fields);
			return $this->m_fields['id'];
		}
		else if (!$this->verify()) // invalid data
		{
		  return false;
		}

		// Update existing record
		return $this->m_table->update($this->m_fields['id'], $this->m_fields, $force_id);
	}

	// ID -------------------------------------------------------------------

	public final function is_saved() { return $this->m_fields['id'] > 0; }
	public final function get_id() { return $this->m_fields['id']; }
	public final function set_id($id) { $this->m_fields['id'] = (int)$id; }

	// Date / time ----------------------------------------------------------

	public final function get_ts() { return $this->m_fields['ts']; }

	/** Set record's timestamp */
	public final function set_ts($ts = null, $save = false)
	{
		if (!$ts) $ts = time();
		$this->m_fields['ts'] = $ts;
		if ($save) $this->write('ts');
	}

	/** Update record's timestamp */
	public final function touch()
	{
		if ($this->m_fields['id'] > 0)
		{
			$this->m_fields['ts'] = time();
			$this->write('ts');
		}
	}

    // Flags ----------------------------------------------------------------

	public final function get_flags() { return (int)$this->m_fields['flags']; }
	public final function has_flag($flag) { return ((int)$this->m_fields['flags'] & (int)$flag) == (int)$flag; }
	public final function clear_flags() { $this->m_fields['flags'] = 0; }

	/** Set flags */
	public final function set_flags($arg, $save = false)
	{
		$this->m_fields['flags'] = (int)$arg;

		if ($save)
			$this->write('flags');
	}

	/** Set or clear flag */
	public final function set_flag($flag, $set, $save = false)
	{
		$set ? ((int)$this->m_fields['flags'] |=  (int)$flag) :
		       ((int)$this->m_fields['flags'] &= ~(int)$flag);

		if ($save)
			$this->write('flags');
	}
}
?>