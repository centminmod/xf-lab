<?php
if (PHP_SAPI !== 'cli' || !is_file('/claims/.synthetic-claims-lab')) { throw new RuntimeException('Synthetic claim lab required'); }
$claimsRoot = getenv('XF_CLAIMS_ROOT');
if (!in_array($claimsRoot, array('/claims/base-webroot', '/claims/wmtech-webroot'), true)) { throw new RuntimeException('Unexpected source root'); }
$claimsWarnings = array();
$claimsRecorder=function ($number, $message) use (&$claimsWarnings) { $claimsWarnings[] = array('number'=>$number, 'message'=>$message); return true; };
set_error_handler($claimsRecorder);
require '/wmtech/1.5.51/tools/bootstrap.php';
xf151_bootstrap($claimsRoot);
$claimsApp=XenForo_Application::getInstance();$claimsConfig=XenForo_Application::getConfig();
$claimsApp->addLazyLoader('cache',array($claimsApp,'loadCache'),$claimsConfig->cache);
$claimsApp->addLazyLoader('options',array($claimsApp,'loadOptions'));
$claimsApp->addLazyLoader('simpleCache',array($claimsApp,'loadSimpleCache'));
XenForo_Application::$jsVersion=substr(md5(XenForo_Application::$versionId.$claimsConfig->jsVersion),0,8);
XenForo_Application::$externalDataPath=(string)$claimsConfig->externalDataPath;
XenForo_Application::$externalDataUrl=(string)$claimsConfig->externalDataUrl;
XenForo_Application::$javaScriptUrl=(string)$claimsConfig->javaScriptUrl;
set_error_handler($claimsRecorder);
if (!in_array(XenForo_Application::getConfig()->db->dbname, array('xf_base','xf_patch'), true)) { throw new RuntimeException('Unexpected synthetic database'); }
XenForo_Phrase::setPhrases(require $claimsRoot.'/install/language_en.php');
XenForo_Application::set('requestPaths', array('basePath'=>'/','fullBasePath'=>'http://localhost:8874/', 'fullUri'=>'http://localhost:8874/', 'host'=>'localhost:8874','protocol'=>'http'));
restore_exception_handler();
error_reporting(E_ALL);
$claimsWarnings = array();

function claims_config(array $replace)
{
	$config=XenForo_Application::getConfig()->toArray();
	foreach ($replace AS $key=>$value) { $config[$key]=$value; }
	XenForo_Application::set('config',new Zend_Config($config));
}
