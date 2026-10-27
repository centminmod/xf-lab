<?php
require __DIR__.'/bootstrap.php';
$nocurl=isset($argv[1]) && $argv[1]==='nocurl';
$curlControl=!function_exists('stream_get_transports');
$result=array('php'=>PHP_VERSION,'version'=>XenForo_Application::$versionId,'curlVersionAvailable'=>function_exists('curl_version'),'forcedPayPalCurlBranch'=>$curlControl);
function claims_options($client){$p=new ReflectionProperty($client,'config');$p->setAccessible(true);return $p->getValue($client);}
if ($nocurl)
{
	$client=XenForo_Helper_Http::getUntrustedClient('https://example.com/');$options=claims_options($client);
	$result['adapter']=$options['adapter'];$result['resolvePresent']=!empty($options['curloptions'][CURLOPT_RESOLVE]);
}
else
{
	$restored=stream_wrapper_unregister('https');
	foreach(array('general','paypal') AS $kind)
	{
		$rows=array();
		foreach(array('untrustedCertificate','trustedCertificate','wrongHostname') AS $case)
		{
			$url=$case==='wrongHostname'?'https://'.gethostbyname('php74').':8443/':'https://php74:8443/';
			$client=$kind==='general'?XenForo_Helper_Http::getClient($url):XenForo_UserUpgradeProcessor_PayPal::getHttpClient($url);
			$options=claims_options($client);$curl=isset($options['curloptions'])?$options['curloptions']:array();
			if($case!=='untrustedCertificate'){
				if($options['adapter']==='Zend_Http_Client_Adapter_Socket'){$client->setAdapter($options['adapter']);$client->getAdapter()->setStreamContext(array('ssl'=>array('cafile'=>'/claims/private/tls.crt')));}
				else{$curl[CURLOPT_CAINFO]='/claims/private/tls.crt';$client->setConfig(array('curloptions'=>$curl));}
			}
			$client->setConfig(array('timeout'=>4));
			try{$response=$client->request('GET');$outcome=array('connected'=>true,'status'=>$response->getStatus());}
			catch(Exception $e){$outcome=array('connected'=>false,'exception'=>get_class($e),'certificateFailure'=>stripos($e->getMessage(),'SSL')!==false || stripos($e->getMessage(),'certificate')!==false);}
			$rows[$case]=$outcome+array('adapter'=>$options['adapter'],'peerFlag'=>isset($curl[CURLOPT_SSL_VERIFYPEER])?$curl[CURLOPT_SSL_VERIFYPEER]:null,'hostnameFlag'=>isset($curl[CURLOPT_SSL_VERIFYHOST])?$curl[CURLOPT_SSL_VERIFYHOST]:null);
		}
		$result[$kind]=$rows;
	}
	if($restored){stream_wrapper_restore('https');}
	if(method_exists('XenForo_Helper_Http','getResolveOptions')){$m=new ReflectionMethod('XenForo_Helper_Http','getResolveOptions');$m->setAccessible(true);$options=$m->invoke(null,'https://example.test/',array('1.1.1.1','8.8.8.8','2001:4860:4860::8888'));$result['duplicateResolveHostPortRules']=count($options['curloptions'][CURLOPT_RESOLVE]);}
}
$suffix=(XenForo_Application::$versionId===1055070?'wmtech':'base').'-'.PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION.($nocurl?'-nocurl':'').($curlControl?'-curl-control':'');
$result['testControlWarnings']=count($claimsWarnings);
$result['controlNote']=$curlControl?'stream_get_transports is disabled only in this CLI process to select the native cURL fallback. Its resulting warnings are test-control artifacts.':($nocurl?'Only curl_version detection is disabled; this tests the exact unpinned fallback condition, not a physically removed extension.':'Native transport selection; the general-client cURL branch is selected by removing the HTTPS stream wrapper within this CLI process.');
file_put_contents('/claims/evidence/transport-'.$suffix.'.json',json_encode($result,JSON_PRETTY_PRINT)."\n");echo json_encode($result,JSON_PRETTY_PRINT),"\n";
