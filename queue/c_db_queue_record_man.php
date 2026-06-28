<?php
// Copyright 2005-2026 Sergey Shustov.
// License: GPL v2.  See License.txt.

/** Queue Record Manager Class */
abstract class CDbQueueRecordMan extends CDbRecordManSingleton
{
    /** Max number of items */
    private $max_items = 1;
    
    /** The constructor */
    protected function __construct($table_name, $class_name)
    {
        parent::__construct($table_name, $class_name);
    }
    
    /** Process the item. Returns true on success */
    protected abstract function process(CDbQueueRecord $item);
    
    /** Get max number of items */
    public function get_max_items()
    {
        return $this->max_items;
    }
    
    /** Set max number of items */
    public function set_max_items($num_items)
    {
        if ($num_items > 0) $this->max_items = $num_items;
    }
    
    /** Pop the queue. Returns number of processed items */
    public function pop_queue()
    {
        $n = 0;
        $result = $this->m_table->get(null, null, false, $this->get_max_items());
        
        if ($result) foreach ($result as $row)
        {
            $item = unserialize($row['contents']);
            
            try
            {
                if ($this->process($item))
                {
                    $this->m_table->delete($row['id']);
                    $n++;
                }
            }
            catch (Exception $e)
            {
            }
        }
        
        return $n;
    }
}
?>