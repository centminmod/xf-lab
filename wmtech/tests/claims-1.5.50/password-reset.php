<?php
require __DIR__.'/bootstrap.php';
$db=XenForo_Application::getDb();$original=$db->fetchRow('SELECT * FROM xf_user_authenticate WHERE user_id=2');
$patched=XenForo_Application::$versionId===1055070;$draw=$patched?16:8;
$dir='/claims/private/mail-reset-'.($patched?'wmtech':'base');if(!is_dir($dir)){mkdir($dir,0700);}foreach(glob($dir.'/*') AS $file){unlink($file);}
claims_config(array('enableMail'=>true,'enableMailQueue'=>false));
XenForo_Mail::setupTransport(new Zend_Mail_Transport_File(array('path'=>$dir)));
$property=new ReflectionProperty('XenForo_Application','_randomData');$property->setAccessible(true);
try
{
	$property->setValue(null,str_repeat("\xFF",3).str_repeat("\0",40));
	$password=XenForo_Model::create('XenForo_Model_UserConfirmation')->resetPassword(2,true);
	$auth=XenForo_Model::create('XenForo_Model_User')->getUserAuthenticationObjectByUserId(2);
	$files=glob($dir.'/*');$contains=false;foreach($files AS $file){$contains=$contains || strpos(quoted_printable_decode(file_get_contents($file)),$password)!==false;}
	$result=array('php'=>PHP_VERSION,'version'=>XenForo_Application::$versionId,'nominalGeneratorLength'=>$draw,'controlledTrimmedPasswordLength'=>strlen($password),'newPasswordAuthenticates'=>$auth->authenticate(2,$password),'wrongPasswordRejected'=>!$auth->authenticate(2,'wrong-synthetic-password'),'localMailFiles'=>count($files),'passwordPresentInLocalMail'=>$contains,'externalDeliveryAttempted'=>false,'passwordValueExported'=>false,'control'=>'Preloaded private random-byte buffer yields four leading underscores before native trim. This is a deterministic length-boundary test, not a claim of predictable production RNG.');
}
finally{$db->update('xf_user_authenticate',$original,'user_id=2');$property->setValue(null,'');}
$result['originalAuthenticationBytesRestored']=$original===$db->fetchRow('SELECT * FROM xf_user_authenticate WHERE user_id=2');
if(!$result['newPasswordAuthenticates'] || !$result['wrongPasswordRejected'] || !$result['passwordPresentInLocalMail'] || !$result['originalAuthenticationBytesRestored'] || $result['localMailFiles']!==1){throw new RuntimeException('Native password-reset control failed');}
file_put_contents('/claims/evidence/password-reset-'.($patched?'wmtech':'base').'.json',json_encode($result,JSON_PRETTY_PRINT)."\n");echo json_encode($result,JSON_PRETTY_PRINT),"\n";
