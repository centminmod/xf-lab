<?php
if(!is_file('/claims/.synthetic-claims-lab')){http_response_code(404);exit;}
$source=isset($_GET['source'])?$_GET['source']:'wmtech';if(!in_array($source,array('base','wmtech'),true)){http_response_code(400);exit;}
$case=isset($_GET['secureConfig'])?$_GET['secureConfig']:'default';if(!in_array($case,array('default','on','off'),true)){http_response_code(400);exit;}
$root='/claims/'.$source.'-webroot';$configDir='/claims/cookie-configs/'.$source.'-'.$case;chdir($root);require $root.'/library/XenForo/Autoloader.php';XenForo_Autoloader::getInstance()->setupAutoloader($root.'/library');XenForo_Application::initialize($configDir,$root);
$policy=isset($_GET['policy'])?$_GET['policy']:null;if($policy!==null && !in_array($policy,array('Lax','Strict','None',''),true)){http_response_code(400);exit;}
if($policy!==null){$config=XenForo_Application::getConfig()->toArray();$config['cookie']['sameSite']=$policy;XenForo_Application::set('config',new Zend_Config($config));}
if(!empty($_GET['delete'])){XenForo_Helper_Cookie::deleteCookie('claims',true);}else{XenForo_Helper_Cookie::setCookie('claims','synthetic-value',300,true);}
header('Content-Type: application/json');echo json_encode(array('php'=>PHP_VERSION,'version'=>XenForo_Application::$versionId,'secure'=>XenForo_Application::$secure));
