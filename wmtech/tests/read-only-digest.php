<?php
require __DIR__.'/xf1-bootstrap.php';
$db=XenForo_Application::getDb();$tables=$db->listTables();sort($tables);
$quoted=array_map(array($db,'quoteIdentifier'),$tables);
$rows=$db->fetchAll('CHECKSUM TABLE '.implode(',',$quoted).' EXTENDED');
if(count($rows)!==count($tables)) {throw new RuntimeException('Incomplete checksum result');}
echo json_encode(array('database'=>hash('sha256',serialize($rows)), 'config'=>hash_file('sha256',XenForo_Application::getInstance()->getConfigDir().'/config.php'))),"\n";
