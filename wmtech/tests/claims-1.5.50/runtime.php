<?php
require __DIR__.'/bootstrap.php';
$db=XenForo_Application::getDb();$patched=XenForo_Application::$versionId===1055070;
$result=array('php'=>PHP_VERSION,'version'=>XenForo_Application::$versionId,'runtime'=>array('curl'=>curl_version()['version'],'libxml'=>LIBXML_DOTTED_VERSION,'openssl'=>OPENSSL_VERSION_TEXT),'probes'=>array());
function claims_probe($name, $callback)
{
	global $claimsWarnings,$result;$claimsWarnings=array();
	try {$value=$callback();$row=array('completed'=>true,'result'=>$value);}
	catch(Throwable $e){$row=array('completed'=>false,'exception'=>get_class($e),'message'=>$e->getMessage());}
	$counts=array();foreach($claimsWarnings AS $warning){$n=(string)$warning['number'];$counts[$n]=isset($counts[$n])?$counts[$n]+1:1;}$row['warningCounts']=$counts;
	$result['probes'][$name]=$row;
}
function claims_client_config($client){$p=new ReflectionProperty($client,'config');$p->setAccessible(true);return $p->getValue($client);}
class ClaimsWakeup { public static $calls=0; public function __wakeup(){self::$calls++;} }
claims_probe('php-mask',function(){return array('stockEnablesDeprecations'=>(bool)((E_ALL | E_STRICT & ~8192)&E_DEPRECATED),'patchedSuppressesDeprecations'=>!((E_ALL|E_STRICT)&~E_DEPRECATED&E_DEPRECATED));});
claims_probe('registry-null-contract',function(){$r=new Zend_Registry();$r['present']='v';$r['null']=null;return array('present'=>$r->offsetExists('present'),'null'=>$r->offsetExists('null'),'missing'=>$r->offsetExists('missing'));});
claims_probe('toggleClass-and-implode',function(){$classes='one two three';XenForo_Template_Helper_Core::toggleClass('two',$classes);return $classes;});
claims_probe('safe-helper',function(){
	$text=array('message'=>'PHP example ;O:8:"stdClass":0:{}');$object=serialize(array('value'=>new ClaimsWakeup()));ClaimsWakeup::$calls=0;
	$decoded=XenForo_Helper_Php::safeUnserialize($object);
	return array('ordinaryArray'=>XenForo_Helper_Php::safeUnserialize(serialize(array('ok'=>1)))===array('ok'=>1),'objectLikeStringAccepted'=>XenForo_Helper_Php::safeUnserialize(serialize($text))===$text,'objectRejected'=>$decoded===false,'objectHooksExecuted'=>ClaimsWakeup::$calls);
});
claims_probe('native-session-object-control',function()use($db){
	$id=str_repeat('c',32);$db->delete('xf_session','session_id='.$db->quote($id));$db->beginTransaction();
	try{$db->insert('xf_session',array('session_id'=>$id,'session_data'=>serialize(array('value'=>new ClaimsWakeup())),'expiry_date'=>time()+300));
	$session=new XenForo_Session();ClaimsWakeup::$calls=0;$data=$session->getSessionFromSource($id);
	return array('returnedArray'=>is_array($data),'objectHooksExecuted'=>ClaimsWakeup::$calls);}
	finally{$db->delete('xf_session','session_id='.$db->quote($id));$db->rollBack();}
});
claims_probe('constant-time-call-contracts',function(){
	$hash=new XenForo_PasswordHash(8,true);$stored=$hash->HashPassword('synthetic-password');
	return array('equal'=>XenForo_Application::hashEquals('abc','abc'),'differentRejected'=>!XenForo_Application::hashEquals('abc','abd'),'missingRejected'=>!XenForo_Application::hashEquals('abc',false),'passwordValid'=>$hash->CheckPassword('synthetic-password',$stored),'wrongPasswordRejected'=>!$hash->CheckPassword('wrong',$stored));
});
claims_probe('proxy-hmac',function(){
	$proxy=(new ReflectionClass('XenForo_ProxyOutput'))->newInstanceWithoutConstructor();$m=new ReflectionMethod($proxy,'_validateHash');$m->setAccessible(true);$url='https://example.test/image.png';
	$signature=hash_hmac('md5',$url,XenForo_Application::getConfig()->globalSalt.XenForo_Application::getOptions()->imageLinkProxyKey);
	return array('valid'=>$m->invoke($proxy,$url,$signature),'wrongRejected'=>!$m->invoke($proxy,$url,str_repeat('0',32)),'missingRejected'=>!$m->invoke($proxy,$url,null));
});
claims_probe('verp-hmac',function(){
	$message=new Zend_Mail_Message(array('raw'=>"From: sender@example.test\r\nTo: receiver@example.test\r\nSubject: test\r\n\r\nSynthetic bounce\r\n"));
	$parser=new XenForo_BounceParser($message,null,'synthetic-key');$m=new ReflectionMethod($parser,'_processRecipient');$m->setAccessible(true);$email='recipient@example.test';$sig=substr(hash_hmac('md5',$email,'synthetic-key'),0,8);
	$m->invoke($parser,'',"\nX-To-Validate: ".$sig.'+'.$email."\n");$valid=$parser->recipientTrusted();
	$m->invoke($parser,'',"\nX-To-Validate: 00000000+".$email."\n");return array('valid'=>$valid,'wrongRejected'=>!$parser->recipientTrusted());
});
claims_probe('rng-source-and-confirmation',function()use($db){
	$random=XenForo_Application::generateRandomString(32);$db->beginTransaction();
	try{$record=XenForo_Model::create('XenForo_Model_UserConfirmation')->generateUserConfirmationRecord(1,'email');return array('randomLength'=>strlen($random),'urlSafe'=>(bool)preg_match('/^[A-Za-z0-9_-]{32}$/D',$random),'source'=>XenForo_Application::getRandomValueSource(),'confirmationKeyLength'=>strlen($record['confirmation_key']));}
	finally{$db->rollBack();}
});
claims_probe('native-captcha-and-change-modifier',function()use($db){
	XenForo_Db::beginTransaction($db);
	try{$captcha=XenForo_Model::create('XenForo_Model_CaptchaQuestion');$method=new ReflectionMethod($captcha,'_insertHash');$method->setAccessible(true);$hash=$method->invoke($captcha,1);
	$change=XenForo_Model::create('XenForo_Model_UserChangeTemp')->applyTempUserChange(2,'groups','',array(2),time()+300,'claim-test');
	$modifier=$db->fetchOne("SELECT action_modifier FROM xf_user_change_temp WHERE change_key='claim-test'");
	return array('captchaLength'=>strlen($hash),'captchaStored'=>(bool)$db->fetchOne('SELECT hash FROM xf_captcha_log WHERE hash=?',$hash),'changeModifierLength'=>strlen($modifier),'modifierPrefix'=>strpos($modifier,'user_change_')===0);}
	finally{XenForo_Db::rollback($db);}
});
claims_probe('session-binding',function(){
	$original=XenForo_Application::getConfig()->toArray();$property=new ReflectionProperty('XenForo_Session','_config');$property->setAccessible(true);$rows=array();
	foreach(array('defaults'=>array(),'adminDisabled'=>array('adminIpv4CidrMatch'=>0),'publicWidened'=>array('ipv4CidrMatch'=>16)) AS $label=>$config){claims_config(array('session'=>$config));foreach(array('public'=>false,'admin'=>true) AS $kind=>$admin){$s=new XenForo_Session(array('admin'=>$admin));$c=$property->getValue($s);$rows[$label][$kind]=array($c['ipv4CidrMatch'],$c['ipv6CidrMatch']);}}
	XenForo_Application::set('config',new Zend_Config($original));return $rows;
});
claims_probe('branding-and-user-agent',function()use($patched){
	$original=XenForo_Application::getConfig()->toArray();$stock=XenForo_Application::getCopyrightHtml();claims_config(array('copyrightHtml'=>''));$blank=XenForo_Application::getCopyrightHtml()==='';claims_config(array('copyrightHtml'=>'Synthetic replacement','httpUserAgent'=>'ClaimsFixture/1.0'));$replacement=XenForo_Application::getCopyrightHtml()==='Synthetic replacement';$c=claims_client_config(XenForo_Helper_Http::getClient('http://example.test/'));
	XenForo_Application::set('config',new Zend_Config($original));return array('stockNonempty'=>$stock!=='','blankOverride'=>$blank,'replacementOverride'=>$replacement,'userAgentOverride'=>$c['useragent']==='ClaimsFixture/1.0');
});
claims_probe('xxe-and-xee',function(){
	$loads=0;libxml_set_external_entity_loader(function()use(&$loads){$loads++;return null;});$tests=array('internal'=>'<!DOCTYPE claim [<!ENTITY e "safe">]><claim>&e;</claim>','file'=>'<!DOCTYPE claim [<!ENTITY e SYSTEM "file:///claims/private/xxe-canary.txt">]><claim>&e;</claim>','network'=>'<!DOCTYPE claim [<!ENTITY e SYSTEM "http://example.test/private">]><claim>&e;</claim>');$r=array();
	foreach($tests AS $name=>$xml){$old=libxml_disable_entity_loader(false);try{$r[$name]=Zend_Xml_Security::scan($xml)===false?'rejected':'accepted';}catch(Zend_Xml_Exception $e){$r[$name]='rejected';}finally{libxml_disable_entity_loader($old);}}
	$xml="\xFF\xFE".mb_convert_encoding($tests['file'],'UTF-16LE','UTF-8');$old=libxml_disable_entity_loader(false);try{$r['utf16']=Zend_Xml_Security::scan($xml)===false?'rejected':'accepted';}catch(Zend_Xml_Exception $e){$r['utf16']='rejected';}finally{libxml_disable_entity_loader($old);libxml_set_external_entity_loader(null);}
	$r['externalLoaderCalls']=$loads;return $r;
});
claims_probe('native-upload-boundaries',function(){
	$path='/claims/private/upload-probe.png';$im=imagecreatetruecolor(2,2);imagepng($im,$path);imagedestroy($im);$good=new XenForo_Upload('probe.txt',$path);$good->setConstraints(array('extensions'=>array('png')));$ok=$good->isValid();
	file_put_contents('/claims/private/not-image.png','Not an image');$bad=new XenForo_Upload('bad.png','/claims/private/not-image.png');$bad->setConstraints(array('extensions'=>array('png')));
	file_put_contents('/claims/private/with-php.png',file_get_contents($path).'<?php /* inert test marker */ ?>');$mixed=new XenForo_Upload('mixed.png','/claims/private/with-php.png');$mixed->setConstraints(array('extensions'=>array('png')));
	return array('validPngAccepted'=>$ok,'extensionCorrected'=>$good->getFileName()==='probe.png','plainTextRejected'=>!$bad->isValid(),'pngWithPhpMarkerRejected'=>!$mixed->isValid());
});
claims_probe('client-ip-spoof-header',function(){$_SERVER['REMOTE_ADDR']='198.51.100.20';$_SERVER['HTTP_X_FORWARDED_FOR']='203.0.113.99';return XenForo_Helper_Ip::convertIpBinaryToString(XenForo_Helper_Ip::getBinaryIp());});
claims_probe('native-csrf',function(){
	XenForo_Visitor::setup(1);$visitor=XenForo_Visitor::getInstance();$time=XenForo_Application::$time;$token='1,'.$time.','.sha1($time.$visitor['csrf_token']);
	$controller=new XenForo_ControllerPublic_Index(new Zend_Controller_Request_Http(),new Zend_Controller_Response_Http(),new XenForo_RouteMatch());$method=new ReflectionMethod($controller,'_checkCsrfFromToken');$method->setAccessible(true);
	return array('valid'=>$method->invoke($controller,$token,false),'wrongRejected'=>!$method->invoke($controller,'1,'.$time.','.str_repeat('0',40),false),'missingRejected'=>!$method->invoke($controller,'',false));
});
claims_probe('redirect-boundaries',function(){
	$r=array();foreach(array('relative'=>'/threads/1','external'=>'https://external.example/path','schemeRelative'=>'//external.example/path','lookalike'=>'http://localhost.evil.example/','userinfo'=>'http://localhost@external.example/','crlf'=>"http://localhost:8874/\r\nX-Test: 1") AS $name=>$target){
		$request=new Zend_Controller_Request_Http();$request->setParam('redirect',$target);$controller=new XenForo_ControllerPublic_Index($request,new Zend_Controller_Response_Http(),new XenForo_RouteMatch());$url=$controller->getDynamicRedirect('http://localhost:8874/fallback',false);$r[$name]=array('fallback'=>$url==='http://localhost:8874/fallback','sameHost'=>parse_url($url,PHP_URL_HOST)==='localhost');
	}return $r;
});
claims_probe('order-by-whitelist',function(){
	$model=XenForo_Model::create('XenForo_Model_User');$choices=array('name'=>'user.username %s');return array('valid'=>$model->getOrderByClause($choices,array('order'=>'name','direction'=>'desc')),'invalidOrder'=>$model->getOrderByClause($choices,array('order'=>'name; DROP TABLE probe','direction'=>'desc'),'user.user_id ASC'),'invalidDirection'=>$model->getOrderByClause($choices,array('order'=>'name','direction'=>'desc; DROP TABLE probe')));
});
claims_probe('native-attachment-hashes',function()use($db){
	XenForo_Visitor::setup(1);$visitor=XenForo_Visitor::getInstance()->toArray();$forum=$db->fetchRow('SELECT * FROM xf_forum WHERE node_id=2');$permissions=XenForo_Visitor::getInstance()->getNodePermissions(2);
	$a=XenForo_Model::create('XenForo_Model_Forum')->getAttachmentParams($forum,array(),$permissions,$visitor);$b=XenForo_Model::create('XenForo_Model_Conversation')->getAttachmentParams(array(),array(),$visitor);
	return array('forumHashLength'=>is_array($a)?strlen($a['hash']):null,'conversationHashLength'=>is_array($b)?strlen($b['hash']):null,'distinctHashes'=>is_array($a)&&is_array($b)&&$a['hash']!==$b['hash']);
});
claims_probe('xfcp-filter',function()use($claimsRoot){
	if(!is_dir($claimsRoot.'/library/ClaimFixture')){mkdir($claimsRoot.'/library/ClaimFixture');}
	file_put_contents($claimsRoot.'/library/ClaimFixture/Extension.php',"<?php class ClaimFixture_Extension extends XFCP_ClaimFixture_Extension {}\n");
	class ClaimsValidBase {} class ClaimsInvalidBase {}
	XenForo_CodeEvent::setListeners(array(),false);XenForo_CodeEvent::addListener('load_class',function($class,&$extend){if($class==='ClaimsValidBase'){$extend[]='ClaimFixture_Extension';}elseif($class==='ClaimsInvalidBase'){$extend[]='Claims_Bad { }';}});
	$valid=XenForo_Application::resolveDynamicClass('ClaimsValidBase');$invalid=null;$error=null;
	try{$invalid=XenForo_Application::resolveDynamicClass('ClaimsInvalidBase');}catch(ParseError $e){$error=get_class($e);}
	finally{XenForo_CodeEvent::setListeners(array(),false);}
	return array('validExtensionResolved'=>$valid==='ClaimFixture_Extension','invalidSkipped'=>$invalid==='ClaimsInvalidBase','invalidParseError'=>$error);
});
foreach($result['probes'] AS $name=>$row){if(!$row['completed']){fwrite(STDERR,$name.': '.$row['exception'].' '.$row['message']."\n");}}
$key=($patched?'wmtech':'base').'-'.PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;
file_put_contents('/claims/evidence/runtime-'.$key.'.json',json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n");echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),"\n";
