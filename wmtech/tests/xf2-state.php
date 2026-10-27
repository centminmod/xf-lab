<?php
if (PHP_SAPI !== 'cli' || !is_file('/review/.xf1551-synthetic-lab')) { throw new RuntimeException('Synthetic CLI lab required'); }
$root=getenv('XF_FORUM_ROOT') ?: '/var/www/html';chdir($root);require $root.'/src/XF.php';XF::start($root);$app=XF::setupApp('XF\Pub\App');
restore_exception_handler();$db=$app->db();$direct=(bool)getenv('XF_FORUM_ROOT');
$old=json_decode(file_get_contents('/review/snapshots/state-'.(getenv('XF_SOURCE_STAGE') ?: ($direct?'direct-1.5.51':'1.5.51')).'.json'),true);
$before=json_decode(file_get_contents('/review/evidence/state-'.(getenv('XF_SOURCE_STAGE') ?: ($direct?'direct-1.5.51':'1.5.51')).'.json'),true);
$fixture=json_decode(file_get_contents('/review/evidence/source-fixture.json'),true);$result=['stage'=>getenv('XF_SOURCE_STAGE') ?: ($direct?'direct-1.5.51-to-2.3.13':'1.5.51-to-2.3.13'),'php'=>PHP_VERSION,'codeVersion'=>XF::$versionId,'databaseVersion'=>(int)$db->fetchOne("SELECT option_value FROM xf_option WHERE option_id='currentVersionId'")];
foreach(['xf_user'=>'users','xf_thread'=>'threads','xf_post'=>'posts','xf_attachment'=>'attachmentCount'] AS $table=>$key) {$result[$key]=(int)$db->fetchOne('SELECT COUNT(*) FROM '.$table);$result[$key.'CountPreserved']=$result[$key]===$before[$key];}
$result['titlePreserved']=$db->fetchOne('SELECT title FROM xf_thread WHERE thread_id=?',$fixture['threadId'])==='Migration fixture & UTF-8 café';
$result['postPreserved']=$db->fetchOne('SELECT message FROM xf_post WHERE post_id=?',$fixture['postId'])==='Stable content [b]bold[/b] with café.';
$attachment=$app->em()->find('XF:AttachmentData',$fixture['attachmentDataId']);$stream=$app->fs()->readStream($attachment->getAbstractedDataPath());$hash=hash_init('sha256');hash_update_stream($hash,$stream);fclose($stream);$result['attachmentBytesPreserved']=hash_final($hash)===$fixture['attachmentSha256'];
foreach([1=>'Admin123Pass',2=>'LegacyUserPass123'] AS $id=>$password) {$auth=$app->em()->find('XF:UserAuth',$id);$result['user'.$id.'PasswordValid']=$auth->authenticate($password);$result['user'.$id.'WrongPasswordRejected']=!$auth->authenticate('wrong-fixture-password');}
$current=$db->fetchAll('SELECT user_id,scheme_class,data FROM xf_user_authenticate ORDER BY user_id');$dataSame=true;$schemes=[];
foreach($current AS $i=>$row) {$dataSame=$dataSame && $row['data']===$old['authRows'][$i]['data'];$schemes[]=$row['scheme_class'];}
$result['serializedAuthBytesPreserved']=$dataSame;$result['authSchemes']=$schemes;
$result['genderMovedToField']=$db->fetchOne("SELECT field_value FROM xf_user_field_value WHERE user_id=2 AND field_id='gender'")==='male';
$result['occupationMovedToField']=$db->fetchOne("SELECT field_value FROM xf_user_field_value WHERE user_id=2 AND field_id='occupation'")==='Migration engineer';
$result['legacyAddonDisabled']=!(bool)$db->fetchOne("SELECT active FROM xf_addon WHERE addon_id='BridgeFixture'");
$result['legacyQueueDropped']=!$db->fetchOne("SHOW TABLES LIKE 'xf_deferred'");
$result['saltPreserved']=$app->config('globalSalt')===$old['salt'];$result['lowercaseCookiePolicy']=$app->config('cookie')['samesite'];
$connected=$app->em()->find('XF:UserProfile',2)->connected_accounts;
foreach(['facebook'=>['app_id'=>'fixture-facebook-id','app_secret'=>'fixture-facebook-secret'],'google'=>['client_id'=>'fixture-google-id','client_secret'=>'fixture-google-secret']] AS $provider=>$expected) {
 $row=$db->fetchRow('SELECT options FROM xf_connected_account_provider WHERE provider_id=?',$provider);
 $options=json_decode($row['options'],true);
 $result['providers'][$provider]=['configPreserved'=>!array_diff_assoc($expected,$options),'linkedUsers'=>(int)$db->fetchOne('SELECT COUNT(*) FROM xf_user_connected_account WHERE provider=?',$provider),'profileCachePresent'=>isset($connected[$provider])];
}
$result['errorLogRows']=(int)$db->fetchOne('SELECT COUNT(*) FROM xf_error_log');
foreach(['usersCountPreserved','threadsCountPreserved','postsCountPreserved','attachmentCountCountPreserved','titlePreserved','postPreserved','attachmentBytesPreserved','user1PasswordValid','user1WrongPasswordRejected','user2PasswordValid','user2WrongPasswordRejected','serializedAuthBytesPreserved','genderMovedToField','occupationMovedToField','legacyAddonDisabled','legacyQueueDropped','saltPreserved'] AS $key) {if(!$result[$key]) {throw new RuntimeException($key);}}
if($result['databaseVersion']!==2031370 || $result['lowercaseCookiePolicy']!=='Lax') {throw new RuntimeException('Wrong target version or cookie mapping');}
foreach($result['providers'] AS $value) {if(!$value['configPreserved']||$value['linkedUsers']!==1||!$value['profileCachePresent']) {throw new RuntimeException('Provider continuity failed');}}
file_put_contents('/review/evidence/state-'.(getenv('XF_RESULT_PREFIX') ?: ($direct?'direct-':'')).'2.3.13.json',json_encode($result,JSON_PRETTY_PRINT)."\n");echo json_encode($result,JSON_PRETTY_PRINT),"\n";
