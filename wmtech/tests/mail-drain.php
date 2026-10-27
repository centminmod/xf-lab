<?php
require __DIR__.'/xf1-bootstrap.php';
$jobs=XenForo_Model::create('XenForo_Model_Deferred');
$before=$jobs->getDeferredByKey('bridge-pending');
if (!$before || hash('sha256',$before['execute_data'])!==trim(file_get_contents('/review/snapshots/bridge-before-51.sha256')))
{
	throw new RuntimeException('Pending business payload changed across the upgrade');
}
$config=XenForo_Application::getConfig()->toArray();$config['enableMail']=true;$config['enableMailQueue']=false;
XenForo_Application::set('config',new Zend_Config($config));
$path='/review/mail-out';if(!is_dir($path)) {mkdir($path,0700);}
foreach(glob($path.'/*') AS $file) {unlink($file);}
XenForo_Mail::setupTransport(new Zend_Mail_Transport_File(array('path'=>$path)));
for($i=0;$i<10;$i++) {
	$continued=$jobs->runByUniqueKey('bridge-pending',0,$status);
	if(!$continued) {break;}
}
if($continued || $jobs->getDeferredByKey('bridge-pending')) {throw new RuntimeException('Business job did not drain');}
$files=glob($path.'/*');
if(count($files)!==2) {throw new RuntimeException('Expected two local mail messages');}
foreach($files AS $file) {
	$body=quoted_printable_decode(file_get_contents($file));
	if(strpos($body,'PHP example ;O:8:"stdClass":0:{}; drain before cutover')===false) {throw new RuntimeException('Mail body changed');}
}
$result=array('payloadBytesPreservedAcrossUpgrade'=>true,'mailEnabledForTest'=>true,'transport'=>'Zend_Mail_Transport_File',
	'messagesWritten'=>count($files),'objectLikeBodyPreserved'=>true,'pendingBusinessJobCount'=>0,'externalDeliveryAttempted'=>false);
file_put_contents('/review/evidence/business-job-drain.json',json_encode($result,JSON_PRETTY_PRINT)."\n");
echo json_encode($result,JSON_PRETTY_PRINT),"\n";
