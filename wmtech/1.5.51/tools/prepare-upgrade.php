<?php
require __DIR__ . '/bootstrap.php';

try
{
	if ($argc < 2 || $argc > 3 || ($argc === 3 && $argv[2] !== '--apply'))
	{
		throw new RuntimeException('Usage: php tools/prepare-upgrade.php /path/to/forum [--apply]');
	}
	$context = xf151_bootstrap($argv[1]);
	$root = $context['root'];
	$path = $root . '/library/config.php';
	if (is_link($path))
	{
		throw new RuntimeException('Review and pin linked config files manually.');
	}
	$original = file_get_contents($path);
	$raw = $context['overrides'];
	$installed = (int) XenForo_Application::getDb()->fetchOne("SELECT option_value FROM xf_option WHERE option_id = 'currentVersionId'");
	$explicit = array_key_exists('globalSalt', $raw);
	$salt = XenForo_Application::getConfig()->globalSalt;
	if (!is_string($salt) || $salt === '' || ($explicit && (!is_string($raw['globalSalt']) || $raw['globalSalt'] === '')))
	{
		throw new RuntimeException('A nonempty string salt is required; review this configuration manually.');
	}
	if (!$explicit && $installed !== XenForo_Application::$versionId)
	{
		throw new RuntimeException('Files and database versions differ. Restore the previous code before pinning its salt.');
	}
	$changed = false;
	if (!$explicit && $argc === 3)
	{
		$inPhp = false;
		foreach (token_get_all($original) AS $token)
		{
			if (is_array($token) && $token[0] === T_OPEN_TAG) { $inPhp = true; }
			if (is_array($token) && $token[0] === T_CLOSE_TAG) { $inPhp = false; }
		}
		$candidate = $original . ($inPhp ? "\n" : "\n<?php\n")
			. "// XF1551: preserve the signing material used before the file upgrade.\n"
			. '$config[\'globalSalt\'] = ' . var_export($salt, true) . ";\n";
		token_get_all($candidate, TOKEN_PARSE);
		$temp = tempnam(dirname($path), '.xf151-');
		if ($temp === false) { throw new RuntimeException('Cannot create the atomic config replacement.'); }
		$phpTemp = $temp . '.php';
		if (!rename($temp, $phpTemp))
		{
			unlink($temp);
			throw new RuntimeException('Cannot protect the temporary config with a PHP extension.');
		}
		$temp = $phpTemp;
		try
		{
			if ((fileowner($temp) !== fileowner($path) && !chown($temp, fileowner($path)))
				|| (filegroup($temp) !== filegroup($path) && !chgrp($temp, filegroup($path)))
				|| !chmod($temp, fileperms($path) & 0777)
				|| file_put_contents($temp, $candidate, LOCK_EX) !== strlen($candidate)
				|| file_get_contents($path) !== $original
				|| !rename($temp, $path))
			{
				throw new RuntimeException('Config changed concurrently or atomic replacement failed.');
			}
			$changed = true;
		}
		finally
		{
			if (is_file($temp)) { unlink($temp); }
		}
	}
	echo json_encode(array('installedVersion' => $installed, 'codeVersion' => XenForo_Application::$versionId,
		'explicitSalt' => $explicit || $changed, 'configChanged' => $changed,
		'action' => $explicit || $changed ? 'ready' : 'run with --apply before uploading new files'), JSON_PRETTY_PRINT) . "\n";
}
catch (Throwable $e)
{
	fwrite(STDERR, $e->getMessage() . "\n");
	exit(1);
}
