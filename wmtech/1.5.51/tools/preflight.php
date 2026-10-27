<?php
require __DIR__ . '/bootstrap.php';

try
{
	if ($argc !== 2) { throw new RuntimeException('Usage: php tools/preflight.php /path/to/forum'); }
	$context = xf151_bootstrap($argv[1]);
	$root = $context['root'];
	$db = XenForo_Application::getDb();
	$raw = $context['overrides'];
	$curl = function_exists('curl_version') ? curl_version() : array();
	$providers = array();
	foreach (array('facebook' => array('facebookAppId', 'facebookAppSecret'), 'google' => array('googleClientId', 'googleClientSecret')) AS $provider => $keys)
	{
		$providers[$provider] = array(
			'idPresent' => (bool) $db->fetchOne('SELECT option_value FROM xf_option WHERE option_id = ?', $keys[0]),
			'secretPresent' => (bool) $db->fetchOne('SELECT option_value FROM xf_option WHERE option_id = ?', $keys[1]),
			'linkedUsers' => (int) $db->fetchOne('SELECT COUNT(*) FROM xf_user_external_auth WHERE provider = ?', $provider)
		);
	}
	$jobs = array();
	$jobCount = 0;
	$statement = $db->query('SELECT deferred_id, execute_class, execute_data, manual_execute, trigger_date FROM xf_deferred ORDER BY deferred_id');
	while ($job = $statement->fetch())
	{
		$value = @unserialize($job['execute_data'], array('allowed_classes' => false));
		$key = $job['execute_class'] . ':' . $job['manual_execute'];
		if (!isset($jobs[$key]))
		{
			$jobs[$key] = array('class' => $job['execute_class'], 'manual' => (bool) $job['manual_execute'],
				'count' => 0, 'heldCount' => 0, 'oldestTriggerDate' => (int) $job['trigger_date'], 'invalidDataCount' => 0, 'invalidIdSample' => array());
		}
		$jobCount++;
		$jobs[$key]['count']++;
		if ((int)$job['trigger_date'] === 2147483647) { $jobs[$key]['heldCount']++; }
		$jobs[$key]['oldestTriggerDate'] = min($jobs[$key]['oldestTriggerDate'], (int) $job['trigger_date']);
		if (!is_array($value) || !xf151_safe_value($value))
		{
			$jobs[$key]['invalidDataCount']++;
			if (count($jobs[$key]['invalidIdSample']) < 100) { $jobs[$key]['invalidIdSample'][] = (int) $job['deferred_id']; }
		}
	}
	$invalidOptions = array();
	foreach ($db->fetchAll("SELECT option_id, option_value FROM xf_option WHERE data_type = 'array'") AS $option)
	{
		$value = @unserialize($option['option_value'], array('allowed_classes' => false));
		if (!is_array($value) || !xf151_safe_value($value)) { $invalidOptions[] = $option['option_id']; }
	}
	$keep = array('db','debug','cookie','enableMail','enableMailQueue','enableListeners','globalSalt','superAdmins',
		'internalDataPath','externalDataPath','externalDataUrl','javaScriptUrl','passwordIterations',
		'enableTemplateModificationCallbacks','enableClickjackingProtection','enableReverseTabnabbingProtection',
		'enableTfa','maxImageResizePixelCount','adminLogLength','checkVersion','chmodWritableValue','rebuildMaxExecution');
	$dropped = array_values(array_diff(array_keys($raw), $keep));
	$obsolete = array();
	foreach (array('fb_channel.php','library/Zend/Service/WindowsAzure','js/swfupload','js/videojs/video-js.swf') AS $path)
	{
		if (file_exists($root . '/' . $path) || is_link($root . '/' . $path)) { $obsolete[] = $path; }
	}
	$paths = array();
	foreach (array('internal_data' => 'internalDataPath', 'data' => 'externalDataPath') AS $label => $key)
	{
		$path = XenForo_Application::getConfig()->$key;
		$paths[$label] = array('exists' => is_dir($path), 'writable' => is_writable($path));
	}
	$result = array(
		'schema' => 'xf1551-preflight-v1',
		'codeVersion' => XenForo_Application::$versionId,
		'databaseVersion' => (int) $db->fetchOne("SELECT option_value FROM xf_option WHERE option_id = 'currentVersionId'"),
		'runtime' => array('php' => PHP_VERSION, 'database' => $db->fetchOne('SELECT VERSION()'),
			'curl' => isset($curl['version']) ? $curl['version'] : null,
			'pinnedHttpSupported' => isset($curl['version_number']) && $curl['version_number'] >= 0x073B00),
		'nativeXf2VersionBoundaryCompatible' => XenForo_Application::$versionId < 2000010,
		'cookieMapping' => array('legacyKeyExplicit'=>isset($raw['cookie']) && array_key_exists('sameSite',$raw['cookie']),
			'lowercaseKeyExplicit'=>isset($raw['cookie']) && array_key_exists('samesite',$raw['cookie']),
			'needsExplicitXf2Policy'=>!isset($raw['cookie']) || !array_key_exists('samesite',$raw['cookie'])),
		'explicitSalt' => isset($raw['globalSalt']) && is_string($raw['globalSalt']) && $raw['globalSalt'] !== '',
		'providers' => $providers,
		'authenticationSchemes' => $db->fetchAll('SELECT scheme_class, COUNT(*) AS users FROM xf_user_authenticate GROUP BY scheme_class'),
		'pendingJobCount' => $jobCount,
		'pendingJobs' => array_values($jobs),
		'mailQueue' => array('count' => (int) $db->fetchOne('SELECT COUNT(*) FROM xf_mail_queue'),
			'oldestQueueDate' => $db->fetchOne('SELECT MIN(queue_date) FROM xf_mail_queue')),
		'invalidArrayOptionIds' => $invalidOptions,
		'addons' => $db->fetchAll('SELECT addon_id, version_id, active FROM xf_addon ORDER BY addon_id'),
		'customStyleTemplates' => (int) $db->fetchOne('SELECT COUNT(*) FROM xf_template WHERE style_id > 0'),
		'droppedConfigKeys' => $dropped,
		'configMapping' => array('cookie.sameSite' => 'cookie.samesite', 'cookie.secure' => 'verify target HTTPS request detection',
			'untrustedHttpClient' => 'review XF2 http.proxy and transport policy', 'paypalSslVerifyPeer' => 'review XF2 http.sslVerify; retain TLS verification',
			'rebuildMaxExecution' => 'jobMaxRunTime'),
		'paths' => $paths,
		'obsoletePaths' => $obsolete,
		'notes' => array('Read-only inventory. No credentials, salts, password hashes or job payloads are printed.',
			'XF2 drops xf_deferred; drain or reconcile every pending business job.',
			'XF2 clears the XF1 mail queue; drain or reconcile queued mail before cutover.',
			'Legacy add-ons/styles require supported successors and manual verification.',
			'PHP version is compatibility metadata, not proof of current engine security patches.')
	);
	echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
}
catch (Throwable $e)
{
	fwrite(STDERR, 'Preflight failed: ' . get_class($e) . '. Check the private installation configuration and database access.' . "\n");
	exit(1);
}
