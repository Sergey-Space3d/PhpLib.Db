<?php
// Copyright 2005-2022 Sergey Shustov.
// License: GPL v2.  See License.txt.

/** Database class */
class CDbase
{
	protected static $m_link = array();
	protected static $m_default_name = null;
	protected static $m_host = null;
	protected static $m_login = null;
	protected static $m_password = null;
	
	public static function get_link($dbname) { return self::$m_link[$dbname]; }
	public static function get_default_name() { return self::$m_default_name; }
	
	/** Initialize database connection */
	public static function connect($host, $login, $password, $def_dbname = null)
	{
		self::$m_host = $host;
		self::$m_login = $login;
		self::$m_password = $password;
		self::$m_default_name = $def_dbname;
	}
	
	/** Open/create database. Returns db link on success, throws exception on failure */
	public static function open($dbname)
	{
	    if (!$dbname) $dbname = self::$m_default_name;
	    if (!$dbname) self::on_error("database name is not set");
	    
	    if (!isset(self::$m_link[$dbname]))
		{
		    // Connect to specific database
		    self::$m_link[$dbname] = @mysqli_connect(self::$m_host, self::$m_login, self::$m_password, $dbname);
		    
		    if (!self::$m_link[$dbname])
		    {
    		    // Cannect to generic database
    		    $link = self::create_link();
    
    		    // Create the database
    		    mysqli_query($link, "CREATE DATABASE IF NOT EXISTS ".$dbname)
    		    or self::on_error("can't create {$dbname}", $link);
    		    
    		    mysqli_close($link);
    		    
    		    // Connect to specific database
    		    self::$m_link[$dbname] = mysqli_connect(self::$m_host, self::$m_login, self::$m_password, $dbname)
    		    or self::on_error("can't connect to {$dbname}");
		    }
		}
		
		return self::$m_link[$dbname];
	}

	/** Create link to access database. Returns db link on success, throws exception on failure */
	public static function create_link()
	{
	    $link = mysqli_connect(self::$m_host, self::$m_login, self::$m_password)
	    or self::on_error("can't connect to ".self::$m_host);
	    return $link;
	}
	
	/** Throw an exception */
	public static function on_error($error, $link = null)
	{
	    if ($link) $link = ': '.mysqli_error($link);
	    throw new Exception("Database failure: {$error}{$link}");
	}
	
	/** Delete database */
	public static function delete($dbname)
	{
	    $link = self::open($dbname);
	    mysqli_query($link, "DROP DATABASE IF EXISTS ".$dbname) 
	    or self::on_error("can't delete {$dbname}", $link);
	    
	    unset(self::$m_link[$dbname]);
	    CDbTable::on_delete_dbase($dbname);
	}
	
	/** Add user to the databases */
	public static function add_user($login, $password, array $dbases)
	{
	    foreach ($dbases as $dbname)
	    {
	        $link = self::open($dbname);
	        mysqli_query($link, "GRANT ALL PRIVILEGES ON {$dbname}.* TO {$login}@".self::$m_host." IDENTIFIED BY '{$password}'")
	        or self::on_error("can't grant priviliges to {$login}", $link);
	    }
	}
	
	/** Remove user from the databases */
	public static function remove_user($login)
	{
	    $link = self::create_link();
	    
	    mysqli_query($link, "DROP USER IF EXISTS {$login}@".self::$m_host)
	    or self::on_error("can't remove user {$login}", $link);

	    mysqli_close($link);
	}
	
	/** Get database names */
	public static function get_databases()
	{
	    $arr = array();
	    $link = self::create_link();
	    $db_list = mysqli_query($link, 'SHOW DATABASES');
	    
	    while ($row = mysqli_fetch_object($db_list))
	    {
	        if (strcasecmp($row->Database, 'mysql') &&
	            strcasecmp($row->Database, 'information_schema'))
	        {
	            $arr[] = $row->Database;
	        }
	    }
	    
	    mysqli_close($link);
	    return $arr;
	}
	
	/** Get database tables */
	public static function get_tables($dbname)
	{
		$arr = array();
	    $link = self::open($dbname);
	    
		// Get table names
	    $tb_result = mysqli_query($link, "SHOW TABLES FROM ".$dbname);

		if ($tb_result)
		{
    		while ($tb_row = mysqli_fetch_row($tb_result))
    		{
    		    $arr[] = $tb_row[0];
    		}
    		
    		mysqli_free_result($tb_result);
		}
		
		return $arr;
	}
}
?>