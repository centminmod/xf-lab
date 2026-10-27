<?php
require __DIR__.'/xf1-bootstrap.php';
$db=XenForo_Application::getDb();if((int)$db->fetchOne("SELECT option_value FROM xf_option WHERE option_id='currentVersionId'")!==1055170) {throw new RuntimeException('Use the isolated restored XF1 clone');}
$before=$db->fetchRow('CHECKSUM TABLE xf_user_confirmation');
$confirm=XenForo_Model::create('XenForo_Model_UserConfirmation');$users=XenForo_Model::create('XenForo_Model_User');$user=$db->fetchRow('SELECT user_id,email FROM xf_user WHERE user_id=2');
XenForo_Db::beginTransaction($db);
try {
 $record=$confirm->generateUserConfirmationRecord(2,'email');$key=$record['confirmation_key'];
 $validBefore=$confirm->validateUserConfirmation(2,'email',$key);$stopBefore=$users->getUserEmailConfirmKey($user);
 $config=XenForo_Application::getConfig()->toArray();$config['globalSalt']='synthetic-rotation-control';XenForo_Application::set('config',new Zend_Config($config));
 $result=array('testedVersionId'=>XenForo_Application::$versionId,'actualStoredEmailVerificationValidBefore'=>$validBefore,'actualStoredEmailVerificationValidAfterSaltChange'=>$confirm->validateUserConfirmation(2,'email',$key),
  'wrongVerificationKeyRejected'=>!$confirm->validateUserConfirmation(2,'email','wrong-synthetic-key'),'emailStopKeyChanged'=>$stopBefore!==$users->getUserEmailConfirmKey($user),'tokenValuesExported'=>false);
} finally {XenForo_Db::rollbackAll($db);}
$result['confirmationTableUnchanged']=$before===$db->fetchRow('CHECKSUM TABLE xf_user_confirmation');
foreach(array('actualStoredEmailVerificationValidBefore','actualStoredEmailVerificationValidAfterSaltChange','wrongVerificationKeyRejected','emailStopKeyChanged','confirmationTableUnchanged') AS $key) {if(!$result[$key]) {throw new RuntimeException($key);}}
file_put_contents('/review/evidence/salt-impact-scope.json',json_encode($result,JSON_PRETTY_PRINT)."\n");echo json_encode($result,JSON_PRETTY_PRINT),"\n";
