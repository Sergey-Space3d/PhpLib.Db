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
	
	/** Initialize database connection */
	public static function connect($host, $login, $password, $def_dbname = null)
	{
		self::$Host = $host;
		self::$Login = $login;
		self::$Password = $password;
		self::$DefaultName = $def_dbname;
		
		mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
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
		        
		    } catch (Exception $e) 
		    {
		        unset(self::$Link[$dbname]);
		    }
		    
		    if (!self::$Link[$dbname])
		    {
    		    // Cannect to generic database
    		    $link = self::create_link();
    
    		    // Create the database
    		    mysqli_query($link, "CREATE DATABASE IF NOT EXISTS ".$dbname)
    		    or self::on_error("can't create {$dbname}", $link);
    		    
    		    mysqli_close($link);
    		    
    		    // Connect to specific database
    		    self::$Link[$dbname] = mysqli_connect(self::$Host, self::$Login, self::$Password, $dbname)
    		    or self::on_error("can't connect to {$dbname}");
		    }
		}
		
		return self::$Link[$dbname];
	}

	/** Create link to access database. Returns db link on success, throws exception on failure */
	public static function create_link()
	{
	    $link = mysqli_connect(self::$Host, self::$Login, self::$Password)
	    or self::on_error("can't connect to ".self::$Host);
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
	    
	    unset(self::$Link[$dbname]);
	    CDbTable::on_delete_dbase($dbname);
	}
	
	/** Add user to the databases */
	public static function add_user($login, $password, array $dbases)
	{
	    $link = self::create_link();
	    $user = "'{$login}'@'".self::$Host."'";
	    
	    try 
	    { 
	        mysqli_query($link, "CREATE USER {$user} IDENTIFIED BY '{$password}'"); 
	    }
	    catch (Exception $e) 
	    {
	        // The user exists - just update the password
	        mysqli_query($link, "SET PASSWORD FOR {$user}=PASSWORD('{$password}')");
	    }

	    mysqli_close($link);
	    
	    foreach ($dbases as $dbname)
	    {
	        $link = self::open($dbname);
	        mysqli_query($link, "GRANT ALL PRIVILEGES ON {$dbname}.* TO {$user} WITH GRANT OPTION")
	        or self::on_error("can't grant priviliges to {$user}", $link);
	    }
	}
	
	/** Remove user from the databases */
	public static function remove_user($login)
	{
	    $link = self::create_link();
	    $user = "'{$login}'@'".self::$Host."'";
	    
	    // Skip exception because of the differences between MySQL 5 and 8
	    try { mysqli_query($link, "REVOKE ALL PRIVILEGES ON *.* FROM {$user}"); }
	    catch (Exception $e) {}
	    
	    try { mysqli_query($link, "DROP USER IF EXISTS {$user}"); }
	    catch (Exception $e) {}

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