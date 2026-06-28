<?php
// Copyright 2005-2026 Sergey Shustov.
// License: GPL v2.  See License.txt.

class CDbBackup
{
    protected $link = null;
    
    /** The constructor */
    public function __construct($host, $login, $password)
    {
        $this->link = mysqli_connect($host, $login, $password);
    }
    
    /** Backup database(s). Throws exception on failure. Returns list of processed tables */
    public function backup($path, array $dbases = null, $tables = null)
    {
        $dbnames = $dbases ? $dbases : $this->get_databases();
        if (!$dbnames) $this->on_error("Failed to get database names");
        
        if (!is_dir($path)) 
        {
            mkdir($path, 0755) or $this->on_error("Failed to make path {$path}");
        }
        else
        {
            self::hide_path($path, false);
        }
        
        $processed = array();
        
        foreach ($dbnames as $dbname)
        {
            if (!$this->is_dbase_included($dbname)) continue;
            
            @set_time_limit(ini_get('max_execution_time'));
            mysqli_select_db($this->link, $dbname);
            
            $copyr = "# Table backup from MySql PHP Backup\n".
                "# Creation date: ".date("d-M-Y h:s")."\n".
                "# Database: ".$dbname."\n".
                "# MySQL Server version: ".mysqli_get_server_info($this->link)."\n\n" ;
            
            $backup_filename = $path."backup.tmp";
            $fp = fopen($backup_filename, "w");
            fwrite($fp, $copyr);
            fclose($fp);
            
            // Get table names
            $tb_result = mysqli_query($this->link, "SHOW TABLES FROM ".$dbname);
            
            if ($tb_result)
            {
                while ($tb_row = mysqli_fetch_row($tb_result))
                {
                    $table = $tb_row[0];
                    if (!$this->is_table_included($table, $tables)) continue;
                    
                    $str = "";
                    $str .= $this->get_def($table);
                    $str .= "\n\n";
                    $str .= $this->get_contents($table);
                    $str .= "\n\n";
                    
                    $fp = fopen($backup_filename, "a");
                    fwrite($fp, $str);
                    fclose($fp);
                    
                    $processed[] = "{$dbname}.{$table}";
                }
                
                mysqli_free_result($tb_result);
            }
            
            $fp = fopen($backup_filename, "a");
            fwrite($fp, "# Valid end of backup from MySql PHP Backup\n");
            fclose($fp);
            
            $gz_filename = $path.$dbname.".sql.gz";
            if (file_exists($gz_filename)) unlink($gz_filename);
            file_put_contents($gz_filename, gzencode(file_get_contents($backup_filename), 9));
            unlink($backup_filename);
        }
        
        self::hide_path($path, true);
        return $processed;
    }
    
    /** Email backup file. Throws exception on failure */
    public function email_backup($path, $mail, array $dbases = null)
    {
        if (!is_dir($path)) $this->on_error("Backup directory not found");
        $dh = opendir($path) or $this->on_error("Failed to open backup directory");

        self::hide_path($path, false);
        
        while (($filename = readdir($dh)) !== false)
        {
            $pathname = $path.$filename; // Path to the file
            if (filetype($pathname) != 'file') continue;
            if (!$this->is_dbase_included($filename, $dbases)) continue;
            $filetype = "application/octet-stream"; // File Type
            
            $file = fopen($pathname, 'rb');
            
            if (!$file)
            {
                self::hide_path($path, true);
                $this->on_error("Failed to open file ".$pathname);
            }
            
            $data = fread($file, filesize($pathname));
            fclose($file);
            
            $semi_rand = md5(time());
            $mime_boundary = "==Multipart_Boundary_x{$semi_rand}x";
            $data = chunk_split(base64_encode($data));
            
            $headers  = "From: {$mail}\n";
            $headers .= "MIME-Version: 1.0\n";
            $headers .= "Content-Type: multipart/mixed;\n";
            $headers .= " boundary=\"{$mime_boundary}\"";
            
            $email_message  = "This is a multi-part message in MIME format.\n\n";
            $email_message .= "--{$mime_boundary}\n";
            $email_message .= "Content-Type:text/html; charset=\"iso-8859-1\"\n";
            $email_message .= "Content-Transfer-Encoding: 7bit\n\n";
            $email_message .= "\n\n";
            $email_message .= "--{$mime_boundary}\n";
            $email_message .= "Content-Type: {$filetype};\n";
            $email_message .= " name=\"{$filename}\"\n";
            $email_message .= "Content-Disposition: attachment;\n";
            $email_message .= " filename=\"{$filename}\"\n";
            $email_message .= "Content-Transfer-Encoding: base64\n\n";
            $email_message .= $data."\n\n";
            $email_message .= "--{$mime_boundary}--\n";
            
            @set_time_limit(ini_get('max_execution_time'));
            $ok = @mail($mail, date('Y-m-d')." backup ".$filename, $email_message, $headers);
            
            if(!$ok)
            {
                self::hide_path($path, true);
                $this->on_error("Failed to send email with backup!");
            }
        }
        
        self::hide_path($path, true);
        closedir($dh);
    }
    
    /** Get table definition */
    protected function get_def($table)
    {
        $def  = "DROP TABLE IF EXISTS ".$table.";#%%\n";
        $def .= "CREATE TABLE ".$table." (\n";
        
        $result = mysqli_query($this->link, "SHOW FIELDS FROM ".$table);
        if (!$result) $this->on_error("Table ".$table." not existing in database");
        
        while ($row = mysqli_fetch_array($result))
        {
            $def .= "    ".$row['Field']." ".$row['Type'];
            if ($row['Default'] != "") $def .= " DEFAULT ".$row['Default'];
            if ($row['Null'] != "YES") $def .= " NOT NULL";
            if ($row['Extra'] != "") $def .= " ".$row['Extra'];
            $def .= ",\n";
        }
        
        mysqli_free_result($result);
        
        $def = preg_replace("/,\n$/", "", $def);
        $result = mysqli_query($this->link, "SHOW KEYS FROM ".$table);
        
        if ($result)
        {
            while ($row = mysqli_fetch_array($result))
            {
                $kname = $row['Key_name'];
                if (($kname != "PRIMARY") && ($row['Non_unique'] == 0)) $kname = "UNIQUE ".$kname;
                
                if (!isset($index[$kname]))
                    $index[$kname] = array();
                    
                $index[$kname][] = $row['Column_name'];
            }
            
            mysqli_free_result($result);
        }
        
        while (list($x, $columns) = @each($index))
        {
            $def .= ",\n";
            if ($x == "PRIMARY") $def .= "   PRIMARY KEY (".implode($columns, ", ").")";
            else if (substr($x, 0, 6) == "UNIQUE") $def .= "   UNIQUE ".substr($x, 7)." (".implode($columns, ", ").")";
            else $def .= "   KEY ".$x." (".implode($columns, ", ").")";
        }
        
        $def .= "\n);#%%";
        return stripslashes($def);
    }
    
    /** Get table contents */
    protected function get_contents($table)
    {
        $content = "";
        mysqli_query($this->link, "LOCK TABLES ".$table." WRITE");
        $result = mysqli_query($this->link, "SELECT * FROM ".$table);
        
        if ($result)
        {
            while ($row = mysqli_fetch_row($result))
            {
                $insert = "INSERT INTO ".$table." VALUES (";
                
                for ($j = 0; $j < mysqli_num_fields($result); $j++)
                {
                    if (!isset($row[$j])) $insert .= "NULL,";
                    else if ($row[$j] != "") $insert .= "'".addslashes($row[$j])."',";
                    else $insert .= "'',";
                }
                
                $insert = preg_replace("/,$/", "", $insert);
                $insert .= ");#%%\n";
                $content .= $insert;
            }
            
            mysqli_free_result($result);
        }
        
        mysqli_query($this->link, "UNLOCK TABLES");
        return $content;
    }
    
    /** Get database names */
    protected function get_databases()
    {
        $arr = array();
        
        if ($this->link)
        {
            $db_list = mysqli_query($this->link, 'SHOW DATABASES');
            
            while ($row = mysqli_fetch_object($db_list))
            {
                if (strcasecmp($row->Database, 'mysql') &&
                    strcasecmp($row->Database, 'information_schema'))
                {
                    $arr[] = $row->Database;
                }
            }
        }
        
        return $arr;
    }
    
    /** Set/unset hide attribute for the path */
    protected static function hide_path($path, $hide)
    {
        @chmod($path, $hide ? 0640 : 0755);
    }
    
    /** Returns true if dbase shall be included in output, false otherwise */
    protected function is_dbase_included($dbstr, array $dbases = null)
    {
        if (!$dbases) return true;
        if (!$dbstr) return false;
        
        foreach ($dbases as $db)
        {
            if (stristr($dbstr, $db))
            {
                return true;
            }
        }
        
        return false;
    }
    
    /** Returns true if table shall be included in output, false otherwise */
    protected function is_table_included($table, array $tables = null)
    {
        if (!$tables) return true;
        if (!$table) return false;
        
        $table = strtolower($table);
        
        foreach ($tables as $t)
        {
            if ($table == strtolower($t))
            {
                return true;
            }
        }
        
        return false;
    }
    
    /** Throw exception on error */
    protected function on_error($error)
    {
        $db_error = mysqli_error($this->link);
        if ($db_error) $db_error = ": {$db_error}";
        throw new Exception("{$error}{$db_error}");
    }
}
?>