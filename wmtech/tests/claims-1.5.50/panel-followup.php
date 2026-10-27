<?php
require '/wmtech/tests/claims-1.5.50/bootstrap.php';
$patched=XenForo_Application::$versionId===1055070;$original=XenForo_Application::getConfig()->toArray();$result=array('php'=>PHP_VERSION,'version'=>XenForo_Application::$versionId);
$ip=array('ip'=>inet_pton('198.51.100.20'));$ip6=array('ip'=>inet_pton('2001:db8:1:2::1'));
foreach(array('default'=>array(),'adminDisabled'=>array('adminIpv4CidrMatch'=>0,'adminIpv6CidrMatch'=>0),'publicWidened'=>array('ipv4CidrMatch'=>16,'ipv6CidrMatch'=>48)) AS $label=>$config){
 claims_config(array('session'=>$config));
 foreach(array('public'=>false,'admin'=>true) AS $kind=>$admin){$session=new XenForo_Session(array('admin'=>$admin));$result['binding'][$label][$kind]=array('sameV4Subnet'=>$session->sessionMatchesIp($ip,inet_pton('198.51.100.99')),'differentV4Subnet'=>$session->sessionMatchesIp($ip,inet_pton('198.51.200.20')),'unrelatedV4'=>$session->sessionMatchesIp($ip,inet_pton('203.0.113.20')),'sameV6Subnet'=>$session->sessionMatchesIp($ip6,inet_pton('2001:db8:1:2::99')),'differentV6Subnet'=>$session->sessionMatchesIp($ip6,inet_pton('2001:db8:1:3::1')));}
}
XenForo_Application::set('config',new Zend_Config($original));
if(!$result['binding']['default']['public']['sameV4Subnet']||$result['binding']['default']['public']['differentV4Subnet']||$result['binding']['default']['public']['differentV6Subnet']){throw new RuntimeException('Default CIDR contract');}
if($result['binding']['adminDisabled']['admin']['unrelatedV4']!==$patched||$result['binding']['adminDisabled']['admin']['differentV6Subnet']!==$patched||$result['binding']['publicWidened']['public']['differentV4Subnet']!==$patched||$result['binding']['publicWidened']['public']['differentV6Subnet']!==$patched){throw new RuntimeException('Override CIDR contract');}
$text='PHP example ;O:8:"stdClass":0:{}';$payload=array('node_id'=>2,'message'=>$text);
foreach(array('report'=>array('XenForo_DataWriter_Report','content_info'),'userCustomFields'=>array('XenForo_DataWriter_User','custom_fields')) AS $label=>$values){
 $writer=XenForo_DataWriter::create($values[0]);try{$writer->set($values[1],$payload);$result['writers'][$label]='accepted';}catch(XenForo_Exception $e){$result['writers'][$label]=strpos($e->getMessage(),'Serialized value contains an object')!==false?'rejectedByExistingRegex':get_class($e);}
 if($result['writers'][$label]!=='rejectedByExistingRegex'){throw new RuntimeException('Unexpected native writer contract');}
}
$model=XenForo_Model::create('XenForo_Model_User');$user=$model->getUserById(2);$user['custom_fields']=serialize(array('synthetic'=>$text));$prepared=$model->prepareUser($user);
$result['legacyReadControls']=array('nativePreparedCustomFieldsIsArray'=>is_array($prepared['customFields']),'payloadPersisted'=>false);
if($result['legacyReadControls']['nativePreparedCustomFieldsIsArray']===$patched){throw new RuntimeException('Unexpected prepared-field result');}
$claimsWarnings=array();$viewingUser=$model->getUserById(1);$handler=new XenForo_ReportHandler_Post();$handler->getVisibleReportsForUser(array(999=>array('content_info'=>serialize($payload))),$viewingUser);
$counts=array();foreach($claimsWarnings AS $warning){$n=(string)$warning['number'];$counts[$n]=isset($counts[$n])?$counts[$n]+1:1;}$result['legacyReadControls']['nativeReportReadWarningCounts']=$counts;
if((!empty($counts['8']))!==$patched){throw new RuntimeException('Unexpected native report notice contract');}
$install=XenForo_Model::create('XenForo_Install_Model_Install');$generated=$install->generateConfig(array('db'=>array('host'=>'synthetic.invalid','username'=>'synthetic')));
$result['configGeneration']=array('dbConfigGenerated'=>strpos($generated,"config['db']")!==false,'saltAutomaticallyInserted'=>strpos($generated,'globalSalt')!==false,'valuesExported'=>false);
if(!$result['configGeneration']['dbConfigGenerated']||$result['configGeneration']['saltAutomaticallyInserted']){throw new RuntimeException('Unexpected generated config contract');}
$result['scope']='Post-panel chair checks. Native sessionMatchesIp with binary test IPs; not a proxy or browser replay. Native report/user writers reject object-looking text on both versions. Direct synthetic legacy-read controls do not establish new member-writable persistence. Generated configuration is inspected in memory only.';
file_put_contents('/claims/evidence/panel-followup-'.($patched?'wmtech':'base').'.json',json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n");echo 'PASS native post-panel checks ',($patched?'original50':'stock24'),"\n";
