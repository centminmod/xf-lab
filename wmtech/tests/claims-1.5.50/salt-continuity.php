<?php
require __DIR__.'/bootstrap.php';
$db=XenForo_Application::getDb();$original=XenForo_Application::getConfig()->toArray();$salts=array();
foreach(array('base','wmtech') AS $label){
	$source=file_get_contents('/claims/source/'.$label.'/upload/library/XenForo/Application.php');
	if(!preg_match("/'globalSalt'\\s*=>\\s*'([^']+)'/",$source,$match)){throw new RuntimeException('Archive default missing');}
	$salts[$label]=$match[1];
}
$user=XenForo_Model::create('XenForo_Model_User');$confirm=XenForo_Model::create('XenForo_Model_UserConfirmation');
$record=$user->getUserById(2);$auth=$user->getUserAuthenticationRecordByUserId(2);$url='https://example.test/image.png';
$proxy=(new ReflectionClass('XenForo_ProxyOutput'))->newInstanceWithoutConstructor();$validate=new ReflectionMethod($proxy,'_validateHash');$validate->setAccessible(true);
$db->beginTransaction();
try{
	claims_config(array('globalSalt'=>$salts['base']));
	$remember=$user->getUserRememberKeyForCookie(2,$auth);$emailStop=$user->getUserEmailConfirmKey($record);
	$signature=hash_hmac('md5',$url,$salts['base'].XenForo_Application::getOptions()->imageLinkProxyKey);
	$nonce=$confirm->generateUserConfirmationRecord(2,'email');
	$before=array('remember'=>$user->loginUserByRememberCookie($remember)===2,'proxy'=>$validate->invoke($proxy,$url,$signature),'storedVerification'=>$confirm->validateUserConfirmation(2,'email',$nonce['confirmation_key']));
	claims_config(array('globalSalt'=>$salts['wmtech']));
	$result=array('php'=>PHP_VERSION,'codeVersion'=>XenForo_Application::$versionId,'archiveDefaultsDiffer'=>$salts['base']!==$salts['wmtech'],'before'=>$before,
		'after'=>array('oldRememberAccepted'=>$user->loginUserByRememberCookie($remember)!==false,'oldProxyAccepted'=>$validate->invoke($proxy,$url,$signature),'emailStopKeyChanged'=>$user->getUserEmailConfirmKey($record)!==$emailStop,'storedVerificationAccepted'=>$confirm->validateUserConfirmation(2,'email',$nonce['confirmation_key'])),
		'valuesExported'=>false,'control'=>'Same native original50 consumers, same synthetic records and URL, only effective archive-default salt changes. Existing active sessions are outside this signature test.');
	if(in_array(false,$before,true)||$result['after']['oldRememberAccepted']||$result['after']['oldProxyAccepted']||!$result['after']['emailStopKeyChanged']||!$result['after']['storedVerificationAccepted']){throw new RuntimeException('Unexpected continuity result');}
}
finally{$db->rollBack();XenForo_Application::set('config',new Zend_Config($original));}
file_put_contents('/claims/evidence/salt-continuity.json',json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n");
echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),"\n";
