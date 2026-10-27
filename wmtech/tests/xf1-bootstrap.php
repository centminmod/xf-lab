<?php
if (PHP_SAPI !== 'cli' || !is_file('/review/.xf1551-synthetic-lab')) { throw new RuntimeException('Synthetic CLI lab required'); }
$xf151Root = getenv('XF_FORUM_ROOT') ?: '/var/www/html';
if ($xf151Root !== '/var/www/html' && strpos($xf151Root, '/review/') !== 0) { throw new RuntimeException('Unexpected lab root'); }
chdir($xf151Root);
require_once $xf151Root . '/library/XenForo/Autoloader.php';
XenForo_Autoloader::getInstance()->setupAutoloader($xf151Root . '/library');
XenForo_Application::initialize($xf151Root . '/library', $xf151Root);
if (!in_array(XenForo_Application::getConfig()->db->dbname, array('xenforo','xf_direct','xf_restore','xf_final','xf_plain','xf_prepared','xf_source_inventory'), true)) { throw new RuntimeException('Unexpected lab database'); }
XenForo_Phrase::setPhrases(require $xf151Root . '/install/language_en.php');
XenForo_Application::set('requestPaths', array('basePath'=>'/','fullBasePath'=>'http://localhost:8774/','fullUri'=>'http://localhost:8774/','host'=>'localhost:8774','protocol'=>'http'));
restore_exception_handler();
