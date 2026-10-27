<?php
require __DIR__.'/xf1-bootstrap.php';
$config=XenForo_Application::getConfig()->toArray();
$config['untrustedHttpClient']=array('timeout'=>19,'maxredirects'=>7,'curloptions'=>array(CURLOPT_CONNECTTIMEOUT=>11,CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_SSL_VERIFYHOST=>0,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_RESOLVE=>array('example.com:443:127.0.0.1')));
XenForo_Application::set('config',new Zend_Config($config));
$client=XenForo_Helper_Http::getUntrustedClient('https://example.com/',array('timeout'=>2));
$reflection=new ReflectionProperty($client,'config');$reflection->setAccessible(true);$options=$reflection->getValue($client);
$curl=$options['curloptions'];
$result=array('adminTimeoutRetained'=>$options['timeout']===19,'ordinaryCurlOptionRetained'=>$curl[CURLOPT_CONNECTTIMEOUT]===11,
 'automaticRedirectsDisabled'=>$options['maxredirects']===0 && $curl[CURLOPT_FOLLOWLOCATION]===false,
 'tlsPeerVerificationEnforced'=>$curl[CURLOPT_SSL_VERIFYPEER]===true && $curl[CURLOPT_SSL_VERIFYHOST]===2,
 'validatedResolveOverridesConfig'=>count($curl[CURLOPT_RESOLVE])===1 && strpos($curl[CURLOPT_RESOLVE][0],'127.0.0.1')===false);
$config['untrustedHttpClient']=array('adapter'=>'Zend_Http_Client_Adapter_Socket','timeout'=>21);
XenForo_Application::set('config',new Zend_Config($config));$custom=XenForo_Helper_Http::getUntrustedClient('https://example.com/');$options=$reflection->getValue($custom);
$result['explicitCustomAdapterRetained']=$options['adapter']==='Zend_Http_Client_Adapter_Socket' && $options['timeout']===21;
foreach($result AS $key=>$ok) {if(!$ok) {throw new RuntimeException($key);}}
file_put_contents('/review/evidence/http-options-final.json',json_encode($result,JSON_PRETTY_PRINT)."\n");
echo json_encode($result,JSON_PRETTY_PRINT),"\n";
