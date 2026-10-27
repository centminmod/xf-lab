<?php
require __DIR__.'/xf1-bootstrap.php';
class Xf151_Deferred_Probe extends XenForo_Deferred_Abstract
{
 public function execute(array $deferred,array $data,$targetRunTime,&$status)
 {
  $db=XenForo_Application::getDb();
  $db->query('UPDATE xf1551_probe SET calls=calls+1 WHERE probe_key=?',$data['probe']);
  if ($data['mode']==='throw') { throw new TypeError('Injected queue fixture failure'); }
  if ($data['mode']==='exit') { exit(42); }
  usleep(300000);
  return false;
 }
}
if (isset($argv[1])) {
 $job=json_decode(file_get_contents('/review/snapshots/job-'.$argv[1].'.json'),true);
 $jobs=XenForo_Model::create('XenForo_Model_Deferred');
 $result=$jobs->runDeferred($job,2,$status,$cancel);
 echo json_encode(array('worker'=>$argv[1],'completed'=>$result===false)),"\n";
}
