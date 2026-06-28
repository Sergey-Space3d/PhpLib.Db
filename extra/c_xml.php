<?php
// Copyright 2005-2026 Sergey Shustov.
// License: GPL v2.  See License.txt.

/** XML helper class */
class CXml
{
    protected static $values = null;
    protected static $indices = null;
    
    /** Parse XML for further processing (optional, for optimizing). Returns true on success */
    public static function begin($xml)
    {
        return self::parse($xml, self::$values, self::$indices);
    }
    
    /** End processing XML (required only if begin() was called) */
    public static function end()
    {
        self::$values = null;
        self::$indices = null;
    }
    
    /** Parse XML, or use previously parsed arrays. Returns true on success */
    protected static function parse($xml, &$values, &$indices)
    {
        if (self::$values !== null)
        {
            $values = self::$values;
            $indices = self::$indices;
            return true;
        }
        
        if ($values === null) $values = array();
        if ($indices === null) $indices = array();
        
        $p = xml_parser_create();
        xml_parser_set_option($p, XML_OPTION_CASE_FOLDING, 1);
        xml_parser_set_option($p, XML_OPTION_SKIP_WHITE, 1);
        $success = xml_parse_into_struct($p, $xml, $values, $indices);
        xml_parser_free($p);
        
        return (bool)$success;
    }
    
    /** Returns associated array of fields specified in the fields parameter */
	public static function get_fields($xml, array $fields, $level = 0)
	{
		$out = array();
		$values = array();
		$indices = array();
		
		if (!self::parse($xml, $values, $indices))
		{
		    return null;
		}
		
		foreach ($fields as $name)
		{
		    $name = strtoupper($name);
		    
			if ($indices[$name])
			{
				foreach ($indices[$name] as $index)
				{
					if ($values[$index]['type'] == 'complete')
					{
					    if ($level == 0 || $values[$index]['level'] == $level)
					    {
					        // Don't allow null, to detect the node's presence
					        $value = $values[$index]["value"];
					        $out[$name] = ($value === null) ? '' : $value;
    						break;
					    }
					}
				}
			}
		}
		
		return $out;
	}
	
	/** Get an array of named nodes, where each node is an array with named properties */
	public static function get_nodes($xml, $node, $parent_node = null, $parent_level = 2)
	{
	    $out = array();
	    $values = array();
	    $indices = array();
	    
	    if (!self::parse($xml, $values, $indices))
	    {
	        return null;
	    }

	    $node = strtoupper($node);
	    if ($parent_node) $parent_node = strtoupper($parent_node);
	    
	    $from = null;
	    $to = null;
	    
	    if ($parent_node)
	    {
	        // Get parent node indicies
	        $val = $indices[$parent_node];
	        if (!$val) return $out;
	        
	        for ($i = 0; $i < count($val); $i++)
	        {
	            $index = $val[$i];
	            $level = $values[$index]['level'];
	            $type = $values[$index]['type'];
	            
	            if ($level == $parent_level && $type == 'open')
	            {
	                // The nodes have to be within range
	                $from = $index + 1;
	                $to = $val[$i + 1] - 1;
	                break;
	            }
	        }
	    }
	    
	    // Get node indicies
	    $val = $indices[$node];
	    if (!$val) return $out;
	    
	    // Each contiguous pair of array entries are the lower and upper range
	    for ($i = 0; $i < count($val); $i++)
	    {
	        $index = $val[$i];
	        $type = $values[$index]['type'];
	        
	        if ($type == 'open')
	        {
	            $offset = $index + 1;
	            $len = $val[$i + 1] - $offset;
	            $i++; // move pass 'close' value

	            if ($from === null || ($offset >= $from && $len > 0 && $offset + $len <= $to))
	            {
	                $out[] = self::parse_node(array_slice($values, $offset, $len));
	            }
	        }
	    }
	    
	    return $out;
	}
	
	/** Parse node into array */
	protected static function parse_node($values)
	{
	    $out = array();
	    
	    for ($i=0; $i < count($values); $i++)
	    {
	        $out[$values[$i]['tag']] = $values[$i]['value'];
	    }
	    
	    return $out;
	}
}
?>