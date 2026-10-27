<?php
require __DIR__.'/xf1-bootstrap.php';
$stage = $argv[1];
if (!preg_match('/^[a-z0-9.-]+$/D', $stage)) { throw new RuntimeException('Invalid stage'); }
$db = XenForo_Application::getDb();
$fixture = json_decode(file_get_contents('/review/evidence/source-fixture.json'), true);
$users = XenForo_Model::create('XenForo_Model_User');
$auth = $users->getUserAuthenticationRecordByUserId(1);
$signatures = array('remember' => $users->prepareRememberKeyForCookie($auth['remember_key']),
	// Historical evidence key: this measures EmailStop/unsubscribe, not account verification.
	'confirmation' => $users->getUserEmailConfirmKey(array('user_id'=>1, 'email'=>'admin@example.test')),
	'proxy' => hash_hmac('md5', 'https://example.com/image.png', XenForo_Application::getConfig()->globalSalt . XenForo_Application::getOptions()->imageLinkProxyKey));
$tables=$db->listTables();sort($tables);$schema=array();
foreach($tables AS $table) {
 $ddl=$db->fetchRow('SHOW CREATE TABLE '.$db->quoteIdentifier($table));
 $schema[$table]=preg_replace('/ AUTO_INCREMENT=[0-9]+/','',array_values($ddl)[1]);
}
$private = array('signatures'=>$signatures, 'salt'=>XenForo_Application::getConfig()->globalSalt,
 'schema'=>hash('sha256',json_encode($schema)), 'authRows'=>$db->fetchAll('SELECT user_id,scheme_class,data FROM xf_user_authenticate ORDER BY user_id'));
file_put_contents('/review/snapshots/state-' . $stage . '.json', json_encode($private));
chmod('/review/snapshots/state-' . $stage . '.json', 0600);
$providers = array();
foreach (array('facebook'=>array('facebookAppId','facebookAppSecret'),'google'=>array('googleClientId','googleClientSecret')) AS $name=>$keys)
{
	$providers[$name] = array('idPresent'=>(bool)$db->fetchOne('SELECT option_value FROM xf_option WHERE option_id=?',$keys[0]),
		'secretPresent'=>(bool)$db->fetchOne('SELECT option_value FROM xf_option WHERE option_id=?',$keys[1]),
		'linkedUsers'=>(int)$db->fetchOne('SELECT COUNT(*) FROM xf_user_external_auth WHERE provider=?',$name));
}
$path = XenForo_Model::create('XenForo_Model_Attachment')->getAttachmentDataFilePath(
	$db->fetchRow('SELECT * FROM xf_attachment_data WHERE data_id=?',$fixture['attachmentDataId']));
$result = array('stage'=>$stage,'codeVersion'=>XenForo_Application::$versionId,
	'databaseVersion'=>(int)$db->fetchOne("SELECT option_value FROM xf_option WHERE option_id='currentVersionId'"),
	'php'=>PHP_VERSION,'users'=>(int)$db->fetchOne('SELECT COUNT(*) FROM xf_user'),
	'threads'=>(int)$db->fetchOne('SELECT COUNT(*) FROM xf_thread'),'posts'=>(int)$db->fetchOne('SELECT COUNT(*) FROM xf_post'),
	'attachmentCount'=>(int)$db->fetchOne('SELECT COUNT(*) FROM xf_attachment'),
	'attachmentPreserved'=>hash_file('sha256',$path)===$fixture['attachmentSha256'],
	'titlePreserved'=>$db->fetchOne('SELECT title FROM xf_thread WHERE thread_id=?',$fixture['threadId'])==='Migration fixture & UTF-8 café',
	'adminPasswordValid'=>$users->getUserAuthenticationObjectByUserId(1)->authenticate(1,'Admin123Pass'),
	'legacyPasswordValid'=>$users->getUserAuthenticationObjectByUserId($fixture['legacyUserId'])->authenticate($fixture['legacyUserId'],'LegacyUserPass123'),
	'wrongPasswordRejected'=>!$users->getUserAuthenticationObjectByUserId(1)->authenticate(1,'incorrect-fixture-password'),
	'providers'=>$providers,
	'pendingBridgeJobs'=>(int)$db->fetchOne("SELECT COUNT(*) FROM xf_deferred WHERE unique_key='bridge-pending'"),
	'errorLogRows'=>(int)$db->fetchOne('SELECT COUNT(*) FROM xf_error_log'));
foreach (array('1.5.24','1.5.50') AS $oldStage)
{
	$file='/review/snapshots/state-'.$oldStage.'.json';
	if (is_file($file))
	{
		$old=json_decode(file_get_contents($file),true);
		$result['schemaMatches-'.$oldStage]=$private['schema']===$old['schema'];
		$result['authStorageMatches-'.$oldStage]=$private['authRows']===$old['authRows'];
		foreach ($signatures AS $name=>$value) { $result['signaturesMatch-'.$oldStage][$name]=($value===$old['signatures'][$name]); }
	}
}
foreach (array('attachmentPreserved','titlePreserved','adminPasswordValid','legacyPasswordValid','wrongPasswordRejected') AS $key)
{
	if (!$result[$key]) { throw new RuntimeException($key.' failed'); }
}
file_put_contents('/review/evidence/state-'.$stage.'.json',json_encode($result,JSON_PRETTY_PRINT)."\n");
echo json_encode($result,JSON_PRETTY_PRINT)."\n";
