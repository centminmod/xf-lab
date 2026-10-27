<?php
require '/review/bootstrap.php';
$config=XenForo_Application::getConfig()->toArray();
$config['cookie']['sameSite']=isset($_GET['legacy'])?$_GET['legacy']:'Lax';
$config['cookie']['samesite']=isset($_GET['lower'])?$_GET['lower']:null;
XenForo_Application::set('config',new Zend_Config($config));
XenForo_Helper_Cookie::setCookie('review','fixture',3600,true,isset($_GET['secure'])&&$_GET['secure']==='1');
echo 'cookie fixture';
