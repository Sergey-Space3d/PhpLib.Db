<?php
// Copyright 2005-2026 Sergey Shustov.
// License: GPL v2.  See License.txt.

/** Logger Class */
class CLogger
{
    const EOF = "\n";
    const FILENAME = 'output.log';
    
    protected $fp;
    protected $filename;
    protected $session_key;
    
    /** Don't allow cloning */
    final private function __clone() {}
    
    /** Get the instance of the class */
    final public static function Instance($filename = null)
    {
        static $instances = array();
        if (!$filename) $filename = CLogger::FILENAME;
        
        if (!isset($instances[$filename]))
        {
            $obj = new static();
            $obj->filename = $filename;
            $obj->session_key = "logger_started_{$filename}";
            $instances[$filename] = $obj;
        }
        
        return $instances[$filename];
    }
    
    /** The constructor */
    protected function __construct()
    {
    }
    
    /** Log line */
    public function log($line)
    {
        if (!$this->fp)
        {
            $mode = $_SESSION[$this->session_key] ? 'a' : 'w';
            $this->fp = fopen($this->filename, $mode);
            $_SESSION[$this->session_key] = true;
        }
        
        if ($line)
        {
            fwrite($this->fp, $line, strlen($line));
            fwrite($this->fp, CLogger::EOF, strlen(CLogger::EOF));
        }
    }
    
    /** Clear log */
    public function clear()
    {
        if ($this->fp)
        {
            fclose($this->fp);
            $this->fp = null;
        }
        
        unlink($this->filename);
        unset($_SESSION[$this->session_key]);
    }
}
?>