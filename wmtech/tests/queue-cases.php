<?php
$workerArgs=$argv;unset($argv[1]);require '/wmtech/tests/queue-worker.php';$argv=$workerArgs;
$db=XenForo_Application::getDb();$jobs=XenForo_Model::create('XenForo_Model_Deferred');
XenForo_Application::$allowManualDeferred=true;
function checkQueue($ok,$label) { if (!$ok) { throw new RuntimeException($label); } }
if ($argv[1]==='setup') {
 $db->query('DROP TABLE IF EXISTS xf1551_probe');
 $db->query('CREATE TABLE xf1551_probe (probe_key VARCHAR(40) PRIMARY KEY, calls INT NOT NULL DEFAULT 0) ENGINE=InnoDB');
 foreach(array('concurrent','shutdown-manual','shutdown-auto') AS $key) {
  $db->insert('xf1551_probe',array('probe_key'=>$key,'calls'=>0));
  $data=array('probe'=>$key,'mode'=>$key==='concurrent'?'normal':'exit','text'=>'PHP ;O:8:"stdClass":0:{}');
  $id=XenForo_Application::defer('Xf151_Deferred_Probe',$data,'probe-'.$key,$key!=='shutdown-auto');
  file_put_contents('/review/snapshots/job-'.$key.'.json',json_encode($jobs->getDeferredById($id)));
 }
 echo "QUEUE_FIXTURE_READY\n";exit;
}
$result=array();
$result['competingWorkersExecutedOnce']=(int)$db->fetchOne("SELECT calls FROM xf1551_probe WHERE probe_key='concurrent'")===1;
foreach(array('shutdown-manual','shutdown-auto') AS $key) {
 $old=json_decode(file_get_contents('/review/snapshots/job-'.$key.'.json'),true);
 $restored=$jobs->getDeferredByKey('probe-'.$key);
 $result[$key.'-dataRetained']=$restored && $restored['execute_data']===$old['execute_data'];
 if($key==='shutdown-auto') { $result['shutdown-auto-backedOff']=$restored['trigger_date']>time()+240; }
 if($restored) { $jobs->deleteDeferredById($restored['deferred_id']); }
}
foreach(array(true,false) AS $manual) {
 $key=$manual?'throw-manual':'throw-auto';$db->insert('xf1551_probe',array('probe_key'=>$key,'calls'=>0));
 $data=array('probe'=>$key,'mode'=>'throw','text'=>'PHP ;O:8:"stdClass":0:{}');
 $id=XenForo_Application::defer('Xf151_Deferred_Probe',$data,'probe-'.$key,$manual);
 $job=$jobs->getDeferredById($id);
 try { $jobs->runDeferred($job,2,$status,$cancel);checkQueue(!$manual,'manual error was swallowed'); }
 catch(TypeError $e) { checkQueue($manual,'automatic error escaped'); }
 $restored=$jobs->getDeferredByKey('probe-'.$key);
 $result[$key.'-dataRetained']=$restored && $restored['execute_data']===$job['execute_data'];
 if(!$manual) { $result['throw-auto-backedOff']=$restored['trigger_date']>time()+240; }
 if($restored) { $jobs->deleteDeferredById($restored['deferred_id']); }
}
foreach(array('malformed'=>'not serialized','object'=>serialize(array('obj'=>new stdClass()))) AS $key=>$payload) {
 $id=XenForo_Application::defer('Xf151_Deferred_Probe',array('probe'=>$key,'mode'=>'normal'),'invalid-'.$key,true);
 $db->update('xf_deferred',array('execute_data'=>$payload),'deferred_id='.$id);
 try { $jobs->runDeferred($jobs->getDeferredById($id),2,$status,$cancel);throw new RuntimeException('Unsafe data executed'); }
 catch(UnexpectedValueException $e) { $result[$key.'-rowRetained']=$jobs->getDeferredById($id)['execute_data']===$payload; }
 $jobs->deleteDeferredById($id);
}
$bad='not serialized';
$id=XenForo_Application::defer('Xf151_Deferred_Probe',array('probe'=>'held-auto','mode'=>'normal'),'held-auto',false);
$db->update('xf_deferred',array('execute_data'=>$bad),'deferred_id='.$id);
$stale=$jobs->getDeferredById($id);$before=(int)$db->fetchOne('SELECT COUNT(*) FROM xf_error_log');
$jobs->runDeferred($stale,2,$status,$cancel);
$held=$jobs->getDeferredById($id);
$result['invalid-auto-held']=$held && $held['execute_data']===$bad && (int)$held['trigger_date']===XenForo_Model_Deferred::HELD_TRIGGER_DATE;
$result['held-auto-not-runnable']=!isset($jobs->getRunnableDeferreds(false)[$id]);
$jobs->runDeferred($stale,2,$status,$cancel);
$result['invalid-auto-logged-once']=(int)$db->fetchOne('SELECT COUNT(*) FROM xf_error_log')===$before+1;
$jobs->deleteDeferredById($id);
$db->insert('xf1551_probe',array('probe_key'=>'producer','calls'=>0));
$old=array('probe'=>'producer','mode'=>'normal','revision'=>1);
$id=$jobs->defer('Xf151_Deferred_Probe',$old,'producer-replace',false);
$stale=$jobs->getDeferredById($id);
$new=$old;$new['revision']=2;$jobs->defer('Xf151_Deferred_Probe',$new,'producer-replace',false);
$jobs->runDeferred($stale,2,$status,$cancel);
$fresh=$jobs->getDeferredByKey('producer-replace');
$result['producer-update-not-deleted']=$fresh && unserialize($fresh['execute_data'],array('allowed_classes'=>false))===$new;
$jobs->runDeferred($fresh,2,$status,$cancel);
$result['producer-latest-data-executed-once']=(int)$db->fetchOne("SELECT calls FROM xf1551_probe WHERE probe_key='producer'")===1;
$db->insert('xf1551_probe',array('probe_key'=>'runner-replaced','calls'=>0));
$data=array('probe'=>'runner-replaced','mode'=>'normal');
$id=$jobs->defer('Xf151_Deferred_Probe',$data,'runner-replaced',false);
$db->update('xf_deferred',array('execute_class'=>'Xf151_Deferred_Missing'),'deferred_id='.$id);
$stale=$jobs->getDeferredById($id);
$db->update('xf_deferred',array('execute_class'=>'Xf151_Deferred_Probe'),'deferred_id='.$id);
$jobs->runDeferred($stale,2,$status,$cancel);
$fresh=$jobs->getDeferredById($id);
$result['producer-runner-replacement-not-held']=$fresh && (int)$fresh['trigger_date']!==XenForo_Model_Deferred::HELD_TRIGGER_DATE;
$jobs->runDeferred($fresh,2,$status,$cancel);
$result['producer-runner-replacement-executed']=(int)$db->fetchOne("SELECT calls FROM xf1551_probe WHERE probe_key='runner-replaced'")===1;
foreach($result AS $key=>$ok) { checkQueue($ok,$key); }
$db->query('DROP TABLE xf1551_probe');
file_put_contents('/review/evidence/queue-recovery.json',json_encode($result,JSON_PRETTY_PRINT)."\n");
echo json_encode($result,JSON_PRETTY_PRINT),"\n";
