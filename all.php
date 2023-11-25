<?php
define('MMDDYYYY', 'm/d/Y');
define('MMDDYYYY_HHMM', 'm/d/Y H:i');
define('MMDDYYYY_HHMMSS', 'm/d/Y H:i:s');
define('YYYYMMDD', 'Y-m-d');
define('YYYYMMDD_HHMM', 'Y-m-d H:i');
define('YYYYMMDD_HHMMSS', 'Y-m-d H:i:s');
define('HHMM', 'H:i');
define('WEEK_TO_SECONDS', 604800);
define('DAY_TO_SECONDS', 86400);
define('HOUR_TO_SECONDS', 3600);

$__dir = dirname(__FILE__);
require_once($__dir.'/c_dbase.php');
require_once($__dir.'/c_db_table.php');
require_once($__dir.'/c_db_label.php');
require_once($__dir.'/c_db_record.php');
require_once($__dir.'/c_db_record_man.php');
require_once($__dir.'/c_db_record_man_singleton.php');

// Setup JIT class autoload
$__sudir = $__dir.'/extra';
$__classes = array(
    'CDbBackup'=>$__sudir.'/c_db_backup.php',
    'CDbRestore'=>$__sudir.'/c_db_restore.php',
    'CDbUpdater'=>$__sudir.'/c_db_updater.php',
    'CLogger'=>$__sudir.'/c_logger.php',
    'CSingleton'=>$__sudir.'/c_singleton.php',
    'CXml'=>$__sudir.'/c_xml.php',
);
require_once($__dir.'/extra/c_autoloader.php');
CAutoloader::register($__classes);

$__sudir = $__dir.'/map';
$__classes = array(
    'CDbLedgerMan'=>$__sudir.'/c_db_ledger_man.php',
    'CDbLedger'=>$__sudir.'/c_db_ledger.php',
    'CDbMapMan'=>$__sudir.'/c_db_map_man.php',
    'CDbMap'=>$__sudir.'/c_db_map.php',
    'CDbMappedLedger'=>$__sudir.'/c_db_mapped_ledger.php',
);
CAutoloader::register($__classes);

$__sudir = $__dir.'/queue';
$__classes = array(
    'CDbQueueRecordMan'=>$__sudir.'/c_db_queue_record_man.php',
    'CDbQueueRecord'=>$__sudir.'/c_db_queue_record.php',
);
CAutoloader::register($__classes);
?>