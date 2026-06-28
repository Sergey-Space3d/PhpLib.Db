<?php
// Copyright 2005-2026 Sergey Shustov.
// License: GPL v2.  See License.txt.

class CAutoloader
{
    private static $_classes = array();
    private static $logging;
    private static $loaded = 0;
    
    /** Setup JIT class autoload. The array consists of class name/file path pairs. */
    public static function register(array $classes, $reset = false)
    {
        if ($reset) self::$_classes = array();
        foreach ($classes as $class=>$path) self::$_classes[strtolower($class)] = $path;
        static $registered = false;
        
        if (!$registered)
        {
            $registered = true;
            
            $fn = function ($name)
            {
                $name = strtolower($name);
                
                foreach (self::$_classes as $class=>$path)
                {
                    if ($name == $class)
                    {
                        if (self::$logging)
                        {
                            CLogger::Instance()->log("Loading {$class} class...");
                        }
                        
                        self::$loaded++;
                        require_once($path);
                        return;
                    }
                }
            };
            
            spl_autoload_register($fn);
        }
    }
    
    /** Enable/disable logging */
    public static function log($enable)
    {
        self::$logging = $enable;
        if ($enable) require_once dirname(__FILE__).'/c_logger.php';
    }
    
    /** Get number of available classes */
    public static function get_num_available() { return count(self::$_classes); }
    
    /** Get number of loaded classes */
    public static function get_num_loaded() { return self::$loaded; }
}
?>