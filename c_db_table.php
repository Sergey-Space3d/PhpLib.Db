<?php
// Copyright 2005-2026 Sergey Shustov.
// License: GPL v2.  See License.txt.

/** Represents database table */
class CDbTable
{
	protected $m_db_name = null;
	protected $m_table_name = null;
	protected static $m_tables = array();
	protected static $m_def_id = 'NULL';
	
	public function get_name() { return $this->m_table_name; }
	public function get_db_name() { return $this->m_db_name; }
	
	/** The constructor */
	public function __construct($full_table_name)
	{
		CDbTable::parse_name($full_table_name, $this->m_db_name, $this->m_table_name);
		CDbase::open($this->m_db_name);

		$key = CDbTable::make_key($this->m_db_name, $this->m_table_name);
		self::$m_tables[$key] = $this;
	}

	/** Create database table */
	public static function create_table($full_table_name, array $field_def)
	{
		$db_name = null;
		$table_name = null;
		CDbTable::parse_name($full_table_name, $db_name, $table_name);
		$key = CDbTable::make_key($db_name, $table_name);

		if (isset(self::$m_tables[$key])) // The table already exists
			return self::$m_tables[$key];

		// Create the table
		$link = CDbase::open($db_name);
		$sql_statement = "CREATE TABLE IF NOT EXISTS ".$table_name." (".CDbTable::make_field_definition($field_def).")";
		
		mysqli_query($link, $sql_statement)
		or CDbase::on_error("creating table {$full_table_name}", $link);

		// Create the table
		return new CDbTable($full_table_name);
	}

	/** Delete database table */
	public static function delete_table($full_table_name)
	{
		$db_name = null;
		$table_name = null;
		CDbTable::parse_name($full_table_name, $db_name, $table_name);

		// Delete the table
		mysqli_query(CDbase::get_link($db_name), "DROP TABLE ".$table_name)
		or CDbase::on_error("deleting table {$full_table_name}", CDbase::get_link($db_name));

		$key = CDbTable::make_key($db_name, $table_name);

		if (isset(self::$m_tables[$key]))
			unset(self::$m_tables[$key]);

		return $ok;
	}
	
	/** Called by CDbase on deletion */
	public static function on_delete_dbase($dbname)
	{
	    $keys = array();
	    
	    // Collect dbase tables
	    foreach (self::$m_tables as $key=>$val)
	    {
	        if (stripos($key, $dbname) === 0)
	        {
	            $keys[] = $key;
	        }
	    }
	    
	    // Delete dbase links
	    foreach ($keys as $key)
	    {
	        unset(self::$m_tables[$key]);
	    }
	}
	
	/** Returns full table's name */
	public static function make_name($db_name, $table_name)
	{
	    return "{$db_name}.{$table_name}";
	}

	/** Parse full table's name to db name and table's name */
	protected static function parse_name($full_table_name, &$db_name, &$table_name)
	{
		$arr = explode('.', $full_table_name);

		if (count($arr) == 1 || !$arr[0])
		{
		    $table_name = $arr[count($arr) - 1];
			$db_name = CDbase::get_default_name();
		}
		else
		{
			$table_name = $arr[1];
			$db_name = $arr[0];
		}
	}

	/** Make key from the names */
	protected static function make_key($db_name, $table_name)
	{
		return $db_name.$table_name;
	}

	/** Make SQL field's definition */
	protected static function make_field_definition(array $field_def)
	{
		// Remove compulsory fields
		if (array_key_exists('id', $field_def)) unset($field_def['id']);
		if (array_key_exists('ts', $field_def)) unset($field_def['ts']);
		if (array_key_exists('flags', $field_def)) unset($field_def['flags']);

		// Define compulsory fields
		$ret = 'id int NOT NULL AUTO_INCREMENT, ts int, flags int, ';

		// Add custom fields
		foreach ($field_def as $key=>$val)
			$ret .= $key.' '.$val.', ';

		// Define the primary key
		$ret .= 'PRIMARY KEY (id)';
		return $ret;
	}

	/** Query database with SQL statement */
	public function query($sql_statement)
	{
	    $link = CDbase::get_link($this->m_db_name);
	    return mysqli_query($link, $sql_statement) or CDbase::on_error(null, $link);
	}

	/** Set default primary key (id) */
	public static function set_default_id($id = 'NULL')
	{
		self::$m_def_id = $id;
	}
	
	/** Add data row. Returns the primary key (id) of the row */
	public function add(array $a_fields)
	{
	    $link = CDbase::get_link($this->m_db_name);
	    
	    // Start table at default id
	    $id = (self::$m_def_id == 'NULL' || !$this->is_empty()) ? 'NULL' : self::$m_def_id;
		
		$use_quote = false;
		$fields = "(id";
		$values = "(".$id;

		foreach ($a_fields as $key => $val)
		{
			if (!$key || $key == 'id')
				continue;

			$fields .= ", ".$key;
			$val = mysqli_real_escape_string($link, $val);

			if ($use_quote == false)
			{
				// ID may not be quoted
				$use_quote = true;
				$values .= ", '".$val;
			}
			else
			{
				$values .= "', '".$val;
			}
		}

		$fields .= ")";
		$values .= "')";

		$sql_statement = "INSERT INTO ".$this->m_table_name." ".$fields." VALUES ".$values;
		mysqli_query($link, $sql_statement) or CDbase::on_error(null, $link);

		// Return the last auto-generated id
		return mysqli_insert_id($link);
	}

	/** Update data row. Returns the primary key (id) of the row */
	public function update($id, array $a_fields, $force_id = false)
	{
	    $link = CDbase::get_link($this->m_db_name);
	    
	    if ($force_id)
		{
			$sql_statement = "INSERT INTO ".$this->m_table_name." (id) VALUES (".$id.")";
			mysqli_query($link, $sql_statement) or CDbase::on_error(null, $link);
		}

		$use_quote = false;
		$str = "";

		foreach ($a_fields as $key => $val)
		{
			if (!$key || $key == 'id')
				continue;

			if ($use_quote == false)
			{
				$use_quote = true;
			}
			else
			{
				$str .= "', ";
			}

			$str .= $key;
			$str .= "='";
			$str .= mysqli_real_escape_string($link, $val);
		}

		$str .= "'";

		$sql_statement = "UPDATE ".$this->m_table_name." SET ".$str." WHERE id=".$id;
		mysqli_query($link, $sql_statement) or CDbase::on_error(null, $link);

		return $id;
	}

	/** Delete rows with specified condition (can be a primary key, numeric) */
	public function delete($condition)
	{
		if (!$condition)
		{
			return false;
		}
		
		if (is_numeric($condition))
		{
			$this->delete_id($condition);
			return true;
		}
		
		if (is_array($condition))
		{
			if (is_numeric($condition['id']))
			{
				$this->delete_id($condition['id']);
				return true;
			}
			
			$result = true;			
			foreach ($condition as $val)
			{
				$result &= $this->delete($val);
			}		
			return $result;
		}
	
		return (bool)mysqli_query(CDbase::get_link($this->m_db_name), "DELETE FROM {$this->m_table_name} WHERE {$condition}");
	}

	/** Delete row with specified primary key (id) */
	protected function delete_id($id)
	{
	    mysqli_query(CDbase::get_link($this->m_db_name), "DELETE FROM {$this->m_table_name} WHERE id={$id}")
	    or CDbase::on_error("deleting {$id} from {$this->m_table_name}", CDbase::get_link($this->m_db_name));
	}

	/** Returns true if row with specified condition exists, false otherwise */
	public function has($condition)
	{
	    return $this->get_fields($condition, null, 'id', 1) ? true : false;
	}
	
	/** Get row(s) with specified condition (null for all rows) */
	public function get($condition = null, $order = null, $id_only = false, $limit_rows = null, $offset_rows = null)
	{
		return $this->get_fields($condition, $order, $id_only ? 'id' : null, $limit_rows, $offset_rows);
	}

	/** Get row fields with specified condition (null for all rows) */
	public function get_fields($condition = null, $order = null, $fields = null, $limit_rows = null, $offset_rows = null)
	{
		$str = null;

		if (!$fields)
			$str = '*';
		else if (is_array($fields))
		{
			foreach ($fields as $val)
			{
				if ($str) $str .= ', ';
				$str .= $val;
			}
		}
		else
			$str = $fields;

		$sql_statement = "SELECT ".$str." FROM ".$this->m_table_name;

		if ($condition && strlen((string)$condition))
			$sql_statement .=" WHERE ".$condition;

		if ($order && strlen((string)$order))
			$sql_statement .= " ORDER BY ".$order;
		else
			$sql_statement .= " ORDER BY id";

		if ($limit_rows > 0)
			$sql_statement .= ' LIMIT '.$limit_rows;

		if ($offset_rows > 0)
			$sql_statement .= ' OFFSET '.$offset_rows;

		// Get the table data
        $result = mysqli_query(CDbase::get_link($this->m_db_name), $sql_statement);

		if (!$result || mysqli_num_rows($result) == 0)
			return null;

		$ret = array();
		while ($row = mysqli_fetch_assoc($result)) { $ret[] = $row; }

		mysqli_free_result($result);
		return $ret;
	}

	/** Get column info */
	public function get_columns()
	{
		$ret = array();
		$result = mysqli_query(CDbase::get_link($this->m_db_name), "SHOW COLUMNS FROM ".$this->m_table_name);
		
		if ($result)
		{
		    while ($row = mysqli_fetch_assoc($result))
    		{
    			$ret[] = $row;
    		}
    
    		mysqli_free_result($result);
		}
		
		return $ret;
	}
	
	/** Inquire if the table is empty */
	public function is_empty()
	{
	    $query = "SELECT 1 FROM {$this->m_table_name}";
	    $result = mysqli_query(CDbase::get_link($this->m_db_name), $query);
	    $is_empty = $result ? $result->num_rows == 0 : true;
	    mysqli_free_result($result);
	    return $is_empty;
	}
	
	/** Get number of rows */
	public function get_num_records($condition = null)
	{
		$query = "SELECT COUNT(*) FROM ".$this->m_table_name;
		if ($condition) $query .= ' WHERE '.$condition;
		$result = mysqli_query(CDbase::get_link($this->m_db_name), $query);
		if (!$result) return 0;
		$row = mysqli_fetch_array($result);
		mysqli_free_result($result);
		return (int)$row[0];
	}
}
?>