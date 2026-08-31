<?php
// Copyright 2005-2026 Sergey Shustov.
// License: GPL v2.  See License.txt.

/** Database class */
class CDbase
{
	protected static $Link = array();
	protected static $DefaultName = null;
	
	public static $Host = null;
	public static $Login = null;
	public static $Password = null;
	
	public static function get_link($dbname) { return self::$Link[$dbname]; }
	public static function get_default_name() { return self::$DefaultName; }
	public static function normalize_user_name($login, $host) { return "'{$login}'@'{$host}'"; }
	
	/** Initialize database connection */
	public static function connect($host, $login, $password, $def_dbname = null)
	{
		self::$Host = $host;
		self::$Login = $login;
		self::$Password = $password;
		self::$DefaultName = $def_dbname;
		
		if (function_exists('mysqli_report')) @mysqli_report(MYSQLI_REPORT_ERROR);
	}
	
	/** Open/create database. Returns db link on success, throws exception on failure */
	public static function open($dbname)
	{
	    if (!$dbname) $dbname = self::$DefaultName;
	    if (!$dbname) self::on_error("database name is not set");
	    
	    if (!isset(self::$Link[$dbname]))
		{
		    try 
		    {
		        // Connect to specific database
		        self::$Link[$dbname] = @mysqli_connect(self::$Host, self::$Login, self::$Password, $dbname);
		    } 
		    catch (Exception $e) 
		    {
		        unset(self::$Link[$dbname]);
		    }
		    
		    if (!self::$Link[$dbname])
		    {
    		    // Cannect to generic database
    		    $link = self::create_link();
    
    		    // Create the database
    		    @mysqli_query($link, "CREATE DATABASE IF NOT EXISTS ".$dbname)
    		    or self::on_error("can't create {$dbname}", $link);
    		    
    		    @mysqli_close($link);
    		    
    		    // Connect to specific database
    		    self::$Link[$dbname] = @mysqli_connect(self::$Host, self::$Login, self::$Password, $dbname)
    		    or self::on_error("can't connect to {$dbname}");
		    }
		}
		
		return self::$Link[$dbname];
	}

	/** Create link to access database. Returns db link on success, throws exception on failure */
	public static function create_link()
	{
	    $link = @mysqli_connect(self::$Host, self::$Login, self::$Password)
	    or self::on_error("can't connect to ".self::$Host);
	    return $link;
	}
	
	/** Throw an exception */
	public static function on_error($error, $link = null)
	{
	    if ($link) $link = ': '.@mysqli_error($link);
	    throw new Exception("Database failure: {$error}{$link}");
	}
	
	/** Delete database */
	public static function delete($dbname)
	{
	    $link = self::open($dbname);
	    @mysqli_query($link, "DROP DATABASE IF EXISTS ".$dbname) 
	    or self::on_error("can't delete {$dbname}", $link);
	    
	    unset(self::$Link[$dbname]);
	    CDbTable::on_delete_dbase($dbname);
	}
	
	/** Add user to the databases */
	public static function add_user($login, $password, array $dbases = null)
	{
	    $link = self::create_link();
	    $user = self::normalize_user_name($login, self::$Host);
	    $has_create_user = true;
	    
	    if (function_exists('mysqli_get_server_info'))
	    {
	    	// NOTE: "CREATE USER" was introduced in MySQL 5.0.2
	    	$v = @mysqli_get_server_info($link);
	    	$has_create_user = (float)substr($v, 0, 3) > 4.9;
	    }
	    
	    if ($has_create_user)
	    {
		    @mysqli_query($link, "CREATE USER {$user} IDENTIFIED BY '{$password}'")
		    or self::on_error("can't create user {$user}", $link);
	    }
	    
	    if ($dbases) foreach ($dbases as $dbname)
	    {
	        $statement = $has_create_user ? 
	        "GRANT ALL PRIVILEGES ON {$dbname}.* TO {$user} WITH GRANT OPTION" :
	        "GRANT ALL PRIVILEGES ON {$dbname}.* TO {$user} IDENTIFIED BY '{$password}'";
	        
	        @mysqli_query($link, $statement)
	        or self::on_error("can't grant priviliges to {$user}", $link);
	        
	        $link2 = self::open($dbname);
	        @mysqli_query($link2, "FLUSH PRIVILEGES");
	        @mysqli_close($link2);
	    }
	    
	    @mysqli_close($link);
	}
	
	/** Remove user from the databases */
	public static function remove_user($login)
	{
	    $link = self::create_link();
	    $user = self::normalize_user_name($login, self::$Host);
	    
	    @mysqli_query($link, "REVOKE ALL PRIVILEGES, GRANT OPTION FROM {$user}")
	    or self::on_error("can't revoke all privileges from user {$user}", $link);
	    
	    @mysqli_query($link, "DROP USER {$user}")
	    or self::on_error("can't drop user {$user}", $link);

	    @mysqli_close($link);
	}
	
	/** Get normalized user names */
	public static function get_users()
	{
		$arr = array();
		$link = self::create_link();
		$result = @mysqli_query($link, 'SELECT User, Host FROM mysql.user ORDER BY User, Host');
		
		while ($row = @mysqli_fetch_object($result))
		{
			$arr[] = self::normalize_user_name($row->User, $row->Host);
		}
		
		@mysqli_close($link);
		return $arr;
	}
	
	/** Get database names */
	public static function get_databases()
	{
	    $arr = array();
	    $link = self::create_link();
	    $db_list = @mysqli_query($link, 'SHOW DATABASES');
	    $reserved_tables = ['mysql', 'information_schema', 'performance_schema'];
	    
	    while ($row = @mysqli_fetch_object($db_list))
	    {
	        if (!in_array($row->Database, $reserved_tables))
	        {
	            $arr[] = $row->Database;
	        }
	    }
	    
	    @mysqli_close($link);
	    return $arr;
	}
	
	/** Get database tables */
	public static function get_tables($dbname)
	{
		$arr = array();
	    $link = self::open($dbname);
	    
		// Get table names
	    $tb_result = @mysqli_query($link, "SHOW TABLES FROM ".$dbname);

		if ($tb_result)
		{
    		while ($tb_row = @mysqli_fetch_row($tb_result))
    		{
    		    $arr[] = $tb_row[0];
    		}
    		
    		@mysqli_free_result($tb_result);
		}
		
		return $arr;
	}
}
?>