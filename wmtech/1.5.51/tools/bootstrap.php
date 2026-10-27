<?php
// CLI helpers stay outside upload/ and never serve forum requests.
if (PHP_SAPI !== 'cli')
{
	http_response_code(404);
	exit;
}

function xf151_bootstrap($root)
{
	$root = realpath($root);
	if (!$root || !is_file($root . '/library/config.php') || !is_file($root . '/library/XenForo/Autoloader.php'))
	{
		throw new RuntimeException('Pass the existing XF1 forum directory.');
	}
	chdir($root);
	require_once $root . '/library/XenForo/Autoloader.php';
	XenForo_Autoloader::getInstance()->setupAutoloader($root . '/library');
	XenForo_Application::initialize($root . '/library', $root, false);
	$raw = xf151_read_config($root . '/library/config.php');
	$app = XenForo_Application::getInstance();
	$effective = new Zend_Config(array(), true);
	$effective->merge($app->loadDefaultConfig())->merge(new Zend_Config($raw))->setReadOnly();
	XenForo_Application::set('config', $effective);
	XenForo_Application::setDebugMode($effective->debug);
	XenForo_Application::set('db', $app->loadDb($effective->db));
	return array('root'=>$root, 'overrides'=>$raw);
}

function xf151_read_config($path)
{
	$config = array();
	require $path;
	return $config;
}

function xf151_safe_value($value, $depth = 0, &$remaining = null)
{
	if ($remaining === null) { $remaining = 100000; }
	if (--$remaining < 0 || is_object($value) || $depth > 128)
	{
		return false;
	}
	if (is_array($value))
	{
		foreach ($value AS $item)
		{
			if (!xf151_safe_value($item, $depth + 1, $remaining))
			{
				return false;
			}
		}
	}
	return true;
}
