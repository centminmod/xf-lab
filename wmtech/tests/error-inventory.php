<?php
require __DIR__.'/xf1-bootstrap.php';
$rows=XenForo_Application::getDb()->fetchAll('SELECT error_id,exception_type,message FROM xf_error_log ORDER BY error_id');
$result=array('rows'=>array(),'unexpected'=>0);
foreach($rows AS $row) {
 $message=$row['message'];$category=null;
 if($message==='Injected queue fixture failure' || preg_match('/^Deferred [0-9]+ has unsafe or invalid array data; its row was retained\.$/D',$message)) {$category='intentional-queue-fault';}
 elseif(strpos($message,"Table 'review.xf_tag' doesn't exist")!==false) {$category='corrected-test-connection-cache';}
 elseif(strpos($message,'Native health or minimum-version check failed')!==false) {$category='corrected-candidate-health-manifest';}
 elseif(strpos($message,'io-fixtures/prepare/install/language_en.php')!==false) {$category='corrected-missing-test-language-fixture';}
 elseif($message==='Undefined index: fullUri' || $message==='Wrong source version' || strpos($message,'The server requested authentication method unknown to the client')!==false) {$category='initial-lab-setup';}
 if($category===null) {$category='unclassified';$result['unexpected']++;}
 $result['rows'][]=array('id'=>(int)$row['error_id'],'type'=>$row['exception_type'],'messageSha256'=>hash('sha256',$message),'category'=>$category);
}
echo json_encode($result,JSON_PRETTY_PRINT),"\n";
if($result['unexpected']) {throw new RuntimeException('Unexpected lab error rows require review');}
