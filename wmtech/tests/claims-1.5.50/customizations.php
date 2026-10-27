<?php
require __DIR__.'/bootstrap.php';
$db=XenForo_Application::getDb();
$mode=$argv[1];$label=XenForo_Application::getConfig()->db->dbname;
$private='/claims/private/custom-'.$label.'.json';
XenForo_Visitor::setup(1);
if ($mode==='seed')
{
	if (XenForo_Application::$versionId!==1052470 || is_file($private)) { throw new RuntimeException('Fresh stock fixture required'); }
	if ((int)$db->fetchOne('SELECT COUNT(*) FROM xf_error_log')!==3) { throw new RuntimeException('Unexpected baseline log rows'); }
	$db->delete('xf_error_log','1=1');
	$addon=XenForo_DataWriter::create('XenForo_DataWriter_AddOn');
	$addon->bulkSet(array('addon_id'=>'ClaimFixture','title'=>'Claim verification fixture','version_string'=>'1.0','version_id'=>1,'active'=>1));$addon->save();
	$style=XenForo_DataWriter::create('XenForo_DataWriter_Style');$style->bulkSet(array('title'=>'Claim child style','parent_id'=>1));$style->save();$styleId=$style->get('style_id');
	$master=$db->fetchRow("SELECT * FROM xf_template WHERE style_id=0 AND title='PAGE_CONTAINER'");
	unset($master['template_id']);$master['style_id']=$styleId;$master['addon_id']='';$master['template'].="\n<!-- claims-child-style -->";
	$template=XenForo_DataWriter::create('XenForo_DataWriter_Template');$template->bulkSet($master);$template->save();
	$mod=XenForo_DataWriter::create('XenForo_DataWriter_TemplateModification');
	$mod->bulkSet(array('addon_id'=>'ClaimFixture','template'=>'PAGE_CONTAINER','modification_key'=>'claims_marker','action'=>'str_replace','find'=>'</body>','replace'=>'<!-- claims-addon-marker -->$0'));$mod->save();
	$language=XenForo_DataWriter::create('XenForo_DataWriter_Language');$language->bulkSet(array('title'=>'Claim translation','parent_id'=>1,'date_format'=>'M j, Y','time_format'=>'g:i A','language_code'=>'fr-FR'));$language->save();$languageId=$language->get('language_id');
	$phrase=$db->fetchRow("SELECT * FROM xf_phrase WHERE language_id=0 AND title='forums'");unset($phrase['phrase_id']);$phrase['language_id']=$languageId;$phrase['phrase_text']='Claim translated forums';
	$writer=XenForo_DataWriter::create('XenForo_DataWriter_Phrase');$writer->bulkSet($phrase);$writer->save();
	XenForo_Model::create('XenForo_Model_Option')->updateOptions(array('defaultStyleId'=>$styleId,'defaultLanguageId'=>$languageId,
		'facebookAppId'=>'fixture-facebook-id','facebookAppSecret'=>'fixture-facebook-secret','googleClientId'=>'fixture-google-id','googleClientSecret'=>'fixture-google-secret','jQuerySource'=>'microsoft','uncompressedJs'=>'2'));
	XenForo_Model::create('XenForo_Model_Phrase')->compileAllPhrases();
	XenForo_Model::create('XenForo_Model_Template')->compileAllTemplates();
	$expected=array('styleId'=>$styleId,'languageId'=>$languageId,'templateBytes'=>$master['template'],'phraseText'=>$phrase['phrase_text']);
	file_put_contents($private,json_encode($expected));chmod($private,0600);
}
elseif ($mode==='upgrade')
{
	if (XenForo_Application::$versionId!==1055070 || (int)$db->fetchOne("SELECT option_value FROM xf_option WHERE option_id='currentVersionId'")!==1052470) { throw new RuntimeException('Unexpected upgrade endpoints'); }
	$upgrade=XenForo_Model::create('XenForo_Install_Model_Upgrade');$remaining=$upgrade->getRemainingUpgradeVersionIds(1052470);
	if ($remaining!==array(1055070)) { throw new RuntimeException('Wrong upgrade sequence'); }
	$version=$upgrade->getUpgrade(1055070);for($step=1;method_exists($version,'step'.$step);$step++){$r=$version->{'step'.$step}();if($r!==null && $r!==true){throw new RuntimeException('Unexpected continuation');}}
	$upgrade->insertUpgradeLog(1055070);
	XenForo_Model::create('XenForo_Install_Model_Install')->insertDeferredRebuild();$jobs=XenForo_Model::create('XenForo_Model_Deferred');
	for($i=0;$i<200;$i++){if(!$continued=$jobs->runByUniqueKey('installUpgradeRebuild',15,$status)){break;}}
	if($continued){throw new RuntimeException('Rebuild not complete');}$upgrade->updateVersion();
}
elseif ($mode!=='snapshot') { throw new RuntimeException('Unknown mode'); }
$expected=json_decode(file_get_contents($private),true);
$tables=$db->listTables();sort($tables);$ddl=array();foreach($tables AS $table){$row=array_values($db->fetchRow('SHOW CREATE TABLE '.$db->quoteIdentifier($table)));$ddl[$table]=preg_replace('/ AUTO_INCREMENT=[0-9]+/','',$row[1]);}
$result=array('php'=>PHP_VERSION,'version'=>XenForo_Application::$versionId,'databaseVersion'=>(int)$db->fetchOne("SELECT option_value FROM xf_option WHERE option_id='currentVersionId'"),
	'tables'=>count($tables),'schemaSha256'=>hash('sha256',json_encode($ddl)),
	'customTemplatePreserved'=>$db->fetchOne("SELECT template FROM xf_template WHERE style_id=? AND title='PAGE_CONTAINER'",$expected['styleId'])===$expected['templateBytes'],
	'translationPreserved'=>$db->fetchOne("SELECT phrase_text FROM xf_phrase WHERE language_id=? AND title='forums'",$expected['languageId'])===$expected['phraseText'],
	'modificationLogs'=>$db->fetchAll("SELECT status,apply_count FROM xf_template_modification_log WHERE modification_id=(SELECT modification_id FROM xf_template_modification WHERE modification_key='claims_marker') ORDER BY template_id"),
	'outdatedTemplates'=>count(XenForo_Model::create('XenForo_Model_Template')->getOutdatedTemplates()),'outdatedPhrases'=>count(XenForo_Model::create('XenForo_Model_Phrase')->getOutdatedPhrases()),
	'masterTemplates'=>(int)$db->fetchOne('SELECT COUNT(*) FROM xf_template WHERE style_id=0'),'masterPhrases'=>(int)$db->fetchOne('SELECT COUNT(*) FROM xf_phrase WHERE language_id=0'),
	'masterTemplateNewVersionRows'=>(int)$db->fetchOne('SELECT COUNT(*) FROM xf_template WHERE style_id=0 AND version_id=1055070'),
	'masterPhraseNewVersionRows'=>(int)$db->fetchOne('SELECT COUNT(*) FROM xf_phrase WHERE language_id=0 AND version_id=1055070'),
	'errorRows'=>(int)$db->fetchOne('SELECT COUNT(*) FROM xf_error_log'));
foreach(array('customTemplatePreserved','translationPreserved') AS $key){if(!$result[$key]){throw new RuntimeException($key);}}
file_put_contents('/claims/evidence/custom-'.$label.'-'.$mode.'.json',json_encode($result,JSON_PRETTY_PRINT)."\n");echo json_encode($result,JSON_PRETTY_PRINT),"\n";
