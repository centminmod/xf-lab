<?php
chdir('/var/www/html');
require 'library/XenForo/Autoloader.php';
XenForo_Autoloader::getInstance()->setupAutoloader('/var/www/html/library');
$method=new ReflectionMethod('XenForo_Helper_Http','getResolveOptions');$method->setAccessible(true);
try { $method->invoke(null,'https://example.com/',array('93.184.216.34'));throw new RuntimeException('Unpinned fallback was permitted'); }
catch(Zend_Http_Client_Exception $e) { echo "PASS unavailable cURL fails closed\n"; }
