<?php
// Copyright 2005-2026 Sergey Shustov.
// License: GPL v2.  See License.txt.

class CDbRestore
{
    protected $host = null;
    protected $login = null;
    protected $password = null;
    
    /** The constructor */
    public function __construct($host, $login, $password)
    {
        $this->host = $host;
        $this->login = $login;
        $this->password = $password;
    }
    
    /** Restore database. Throws exception on failure */
    public function restore($path, $dbname)
    {
        @set_time_limit(ini_get('max_execution_time'));
        $gzfilename = self::gzfilename_from_dbname($path, $dbname);
        
        @chmod($path, 0755);

        try
        {
            $filename = self::unzip_file($gzfilename);
            $file = fread(fopen($filename, "r"), filesize($filename));
            $result = explode(";#%%\n", $file);
            
            if (!$result)
            {
                throw new Exception("Invalid database archive {$gzfilename}");
            }
            
            $link = mysqli_connect($this->host, $this->login, $this->password, $dbname);
            
            foreach ($result as $row)
            {
                if (!mysqli_query($link, $row))
                {
                    throw new Exception("Failed to retore database {$dbname}: ".mysqli_error($link));
                }
            }
            
            mysqli_close($link);
            unlink($filename);
        }
        catch(Exception $e)
        {
            @chmod($path, 0640);
            throw $e;
        }
        
        @chmod($path, 0640);
    }
    
    /** Make GZ filename from db name */
    public static function gzfilename_from_dbname($path, $dbname)
    {
        return $path.$dbname.".sql.gz";
    }
    
    /** Unzip GZ file. Returns filename on success, throws exception on failure */
    protected static function unzip_file($gzfilename)
    {
        if (!substr($gzfilename, -3, 3) == '.gz')
        {
            throw new Exception("File {$gzfilename} doesn't have GZ extension");
        }
        
        $filename = substr($gzfilename, 0, strlen($gzfilename) - 3);
        @unlink($filename);
        
        $fp = @fopen($filename, "w");
        fwrite($fp, "");
        fclose($fp);
        
        $fp = @fopen($filename, "w");
        
        if (!$fp) 
        {
            throw new Exception("Failed to open SQL file {$filename}");
        }
        
        $zp = @gzopen($gzfilename, "rb");
        
        if (!$zp) 
        {
            throw new Exception("Cannot read ZIP archive {$gzfilename}");
        }
        
        while (!gzeof($zp))
        {
            $data = gzgets($zp, 8192);
            fwrite($fp, $data);
        }
        
        fclose($fp);
        gzclose($zp);
        
        return $filename;
    }
}
?>