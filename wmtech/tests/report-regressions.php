<?php
require __DIR__.'/xf1-bootstrap.php';
$stage=$argv[1];
$db=XenForo_Application::getDb();
if (!is_file('/review/.xf1551-synthetic-lab')) { throw new RuntimeException('Destructive scratch probes require the dedicated synthetic lab marker.'); }
$result=array('stage'=>$stage,'php'=>PHP_VERSION);
$mysql=Zend_Db::factory('Mysqli',array('host'=>'mysql8','username'=>'root','password'=>'root','dbname'=>'review','charset'=>'utf8'));
$mysql->query('DROP TABLE IF EXISTS xf_tag');
$tables=XenForo_Install_Data_MySql::getTables();
$mysql->query($tables['xf_tag']);
$mysql->insert('xf_tag',array('tag'=>'cafe variant','tag_url'=>'cafe-2'));
XenForo_Application::set('db',$mysql);
$result['mysqlVersion']=$mysql->fetchOne('SELECT VERSION()');
foreach (array(array('XenForo_Model_Tag','_getUrlVersionOfTag'),array('XenForo_DataWriter_Tag','_getUrlVersion')) AS $test)
{
	$object=new $test[0]();
	$method=new ReflectionMethod($test[0],$test[1]);$method->setAccessible(true);
	foreach(array('cafe','café','CAFE') AS $text)
	{
		try { $result['tagSlugs'][$test[0]][$text]=array('value'=>$method->invoke($object,$text)); }
		catch(Zend_Db_Exception $e) { $result['tagSlugs'][$test[0]][$text]=array('error'=>get_class($e),'mysqlErrno'=>$mysql->getConnection()->errno, 'message'=>$e->getMessage(), 'mysql3995'=>$mysql->getConnection()->errno===3995 || strpos($e->getMessage(),'3995')!==false); }
	}
}
$mysql->query('DROP TABLE xf_tag');
XenForo_Application::set('db',$db);
XenForo_Application::$allowManualDeferred=true;
$jobs=XenForo_Model::create('XenForo_Model_Deferred');
$data=array('criteria'=>array(),'email'=>array('email_title'=>'PHP example','email_body'=>'PHP example ;O:8:"stdClass":0:{}'));
$id=XenForo_Application::defer('UserEmail',$data,'report-regression-probe',true);
$job=$jobs->getDeferredById($id);
$result['queueWritePreserved']=$job && unserialize($job['execute_data'],array('allowed_classes'=>false))===$data;
try {
	$result['queueExecuted']=$jobs->runDeferred($job,1,$status,$cancel)===false;
} catch(Throwable $e) {
	$result['queueError']=get_class($e);
	try { $jobs->shutdownHandleFatalDeferred(); }
	catch(Throwable $recovery) { $result['recoveryError']=get_class($recovery); }
	$property=new ReflectionProperty('XenForo_Model_Deferred','_runningDeferred');$property->setAccessible(true);$property->setValue(null,false);
}
$result['queueRowAfterProbe']=(bool)$jobs->getDeferredByKey('report-regression-probe');
if ($result['queueRowAfterProbe']) { $jobs->deleteDeferredById($jobs->getDeferredByKey('report-regression-probe')['deferred_id']); }
foreach(array('https://example.com/','https://www.php.net/') AS $url)
{
	$check=array();$client=null;
	try {
		$client=XenForo_Helper_Http::getUntrustedClient($url,array('timeout'=>4));
		$check['status']=$client->request('GET')->getStatus();
	} catch(Throwable $e) { $check['error']=get_class($e);$check['message']=$e->getMessage(); }
	$adapter=$client?$client->getAdapter():null;
	$options=$adapter instanceof Zend_Http_Client_Adapter_Curl?$adapter->getConfig():array();
	$check['resolveRules']=isset($options['curloptions'][CURLOPT_RESOLVE])?$options['curloptions'][CURLOPT_RESOLVE]:array();
	$result['http'][$url]=$check;
}
file_put_contents('/review/evidence/regressions-'.$stage.'.json',json_encode($result,JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
echo json_encode($result,JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),"\n";
