<?php
// Copyright 2005-2026 Sergey Shustov.
// License: GPL v2.  See License.txt.

/** Database Ledger Manager Class */
abstract class CDbLedgerMan extends CDbRecordManSingleton
{
    protected $m_map_man = null;
    protected $m_item_class_name = null;

    /** The constructor */
    protected function __construct($table_name, $map_table_name, $item_class_name, $ledger_class_name = 'CDbLedger')
    {
        $this->m_item_class_name = $item_class_name;
        
        parent::__construct($table_name, $ledger_class_name);
        
        $this->m_map_man = new CDbMapMan($map_table_name);
        $this->add_id_reference($map_table_name, 'id1');
    }
    
    /** Returns instance of the class. Override if the constructor arguments are required */
    protected function make_instance()
    {
        return new $this->m_class_name($this->m_table_name);
    }
    
    /** Returns true if the ledger is unique */
    public function is_duplicate(CDbLedger $ledger)
    {
        return $this->m_table->has($ledger->get_duplicate_condition());
    }
    
    /** Create ledger. Returns instance on success, null otherwise */
    public function create($name, $desc = null, $unique = false, $extra = null)
    {
        $ledger = $this->make_instance();
        $ledger->set_name($name);
        $ledger->set_desc($desc);
        
        if ($unique && $this->is_duplicate($ledger))
        {
            return null;
        }
        
        return $ledger->write() ? $ledger : null;
    }
    
    /** Remove ledger items. The ledger may be id, or instance */
    public function clear($ledger_id)
    {
        $this->m_map_man->unmap($ledger_id);
    }
    
    /** Map ledger and item. The ledger may be id, or instance, the item may be an array */
    public function map($ledger_id, $item_id)
    {
        $this->m_map_man->map($ledger_id, $item_id);
    }
    
    /** Unmap ledger and item. The ledger may be id, or instance, the item may be an array */
    public function unmap($ledger_id, $item_id)
    {
        $this->m_map_man->unmap($ledger_id, $item_id);
    }
    
    /** Returns true if the mapping exists, false otherwise */
    public function has($ledger_id, $item_id)
    {
        return $this->m_map_man->has($ledger_id, $item_id);
    }
    
    /** Get items by ledger's id, or instance. Returns array of instances/ids */
    public function get_by_ledger($ledger_id, $return_instances = true)
    {
        $items = array();
        $maps = $this->m_map_man->get_by_id1($ledger_id);
        
        if ($maps)
        {
            foreach ($maps as $map)
            {
                $id = $map->get_id2();
                $items[$id] = $return_instances ? new $this->m_item_class_name($id) : $id;
            }
        }
        
        return $items;
    }
    
    /** Get ledgers by item's id, or instance. Returns array of instances/ids */
    public function get_by_item($item_id, $return_instances = true)
    {
        $ledgers = array();
        $maps = $this->m_map_man->get_by_id2($item_id);
        
        if ($maps)
        {
            foreach ($maps as $map)
            {
                $id = $map->get_id1();
                $ledgers[$id] = $return_instances ? new $this->m_class_name($this->m_table_name, $id) : $id;
            }
        }
        
        return $ledgers;
    }
    
    /** Get ledger by id */
    public function get_ledger($id)
    {
        return new $this->m_class_name($this->m_table_name, $id);
    }
    
    /** Get ledger's id by name */
    public function get_ledger_by_name($name)
    {
        $name = strtolower($name);
        $result = $this->m_table->get("lower(_name)='{$name}'", null, true, 1);
        
        if ($result)
        {
            $row = array_shift($result);
            return $row['id'];
        }
        
        return 0;
    }
}
?>