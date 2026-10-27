<?php
if (PHP_SAPI !== 'cli' || !is_file('/review/.xf1551-synthetic-lab')) { throw new RuntimeException('Synthetic CLI lab required'); }
$label=$argv[1];$roots=array('plain'=>'/review/plain-webroot','prepared'=>'/review/prepared-webroot','staged'=>'/review/final-webroot');
$databases=array('plain'=>'xf_plain','prepared'=>'xf_prepared','staged'=>'xf_final');
if(!isset($roots[$label]) || getenv('XF_FORUM_ROOT')!==$roots[$label]) {throw new RuntimeException('Unexpected comparison root');}
$root=$roots[$label];chdir($root);require $root.'/src/XF.php';XF::start($root);$app=XF::setupApp('XF\Pub\App');restore_exception_handler();
if($app->config('db')['dbname']!==$databases[$label]) {throw new RuntimeException('Unexpected comparison database');}
ini_set('memory_limit','512M');$db=$app->db();
$baseline=json_decode(file_get_contents('/review/snapshots/state-1.5.24.json'),true);$fixture=json_decode(file_get_contents('/review/evidence/source-fixture.json'),true);
$functional=array('version'=>XF::$versionId,'databaseVersion'=>(int)$db->fetchOne("SELECT option_value FROM xf_option WHERE option_id='currentVersionId'"),
 'baselineSaltPreserved'=>$app->config('globalSalt')===$baseline['salt'],'cookiePolicy'=>$app->config('cookie')['samesite']??null,
 'titlePreserved'=>$db->fetchOne('SELECT title FROM xf_thread WHERE thread_id=1')==='Migration fixture & UTF-8 café',
 'postPreserved'=>$db->fetchOne('SELECT message FROM xf_post WHERE post_id=1')==='Stable content [b]bold[/b] with café.',
 'legacyAddonDisabled'=>!(bool)$db->fetchOne("SELECT active FROM xf_addon WHERE addon_id='BridgeFixture'"),
 'legacyQueueDropped'=>!$db->fetchOne("SHOW TABLES LIKE 'xf_deferred'"),'errorLogRows'=>(int)$db->fetchOne('SELECT COUNT(*) FROM xf_error_log'));
foreach(array('xf_user'=>'users','xf_thread'=>'threads','xf_post'=>'posts','xf_attachment'=>'attachments') AS $table=>$key) {$functional[$key]=(int)$db->fetchOne('SELECT COUNT(*) FROM '.$table);}
$permissions=array();$definitions=$db->fetchAll('SELECT permission_group_id,permission_id FROM xf_permission ORDER BY permission_group_id,permission_id');
foreach(array(1=>'Admin123Pass',2=>'LegacyUserPass123') AS $id=>$password) {
 $auth=$app->em()->find('XF:UserAuth',$id);$functional['user'.$id.'PasswordValid']=$auth->authenticate($password);$functional['user'.$id.'WrongPasswordRejected']=!$auth->authenticate('wrong-fixture-password');
 $user=$app->em()->find('XF:User',$id);$permissions[$id]=array('global'=>array(),'node2'=>array());
 foreach($definitions AS $definition) {$g=$definition['permission_group_id'];$p=$definition['permission_id'];$permissions[$id]['global'][$g.'.'.$p]=(bool)$user->hasPermission($g,$p);if($g==='forum') {$permissions[$id]['node2'][$p]=(bool)$user->hasNodePermission(2,$p);}}
}
$rawAuth=$db->fetchAll('SELECT user_id,scheme_class,data FROM xf_user_authenticate ORDER BY user_id');$authSame=true;
foreach($rawAuth AS $i=>$row) {$authSame=$authSame && $row['data']===$baseline['authRows'][$i]['data'];}
$functional['serializedAuthBytesPreserved']=$authSame;$functional['authSchemes']=array_column($rawAuth,'scheme_class');
$functional['genderPreserved']=$db->fetchOne("SELECT field_value FROM xf_user_field_value WHERE user_id=2 AND field_id='gender'")==='male';
$functional['occupationPreserved']=$db->fetchOne("SELECT field_value FROM xf_user_field_value WHERE user_id=2 AND field_id='occupation'")==='Migration engineer';
$functional['attachmentsSha256']=array();foreach($db->fetchAllColumn('SELECT data_id FROM xf_attachment_data ORDER BY data_id') AS $id) {$entity=$app->em()->find('XF:AttachmentData',$id);$stream=$app->fs()->readStream($entity->getAbstractedDataPath());$context=hash_init('sha256');hash_update_stream($context,$stream);fclose($stream);$functional['attachmentsSha256'][$id]=hash_final($context);}
$connected=$app->em()->find('XF:UserProfile',2)->connected_accounts;
foreach(array('facebook'=>array('app_id'=>'fixture-facebook-id','app_secret'=>'fixture-facebook-secret'),'google'=>array('client_id'=>'fixture-google-id','client_secret'=>'fixture-google-secret')) AS $provider=>$expected) {
 $options=json_decode($db->fetchOne('SELECT options FROM xf_connected_account_provider WHERE provider_id=?',$provider),true);
 $functional['providers'][$provider]=array('configPreserved'=>!array_diff_assoc($expected,$options),'linkedUsers'=>(int)$db->fetchOne('SELECT COUNT(*) FROM xf_user_connected_account WHERE provider=?',$provider),'profileCachePresent'=>isset($connected[$provider]));
}
$private='/review/snapshots/path-'.$label;if(!is_dir($private)) {mkdir($private,0700);}
$schema=array();$tables=$db->fetchAllColumn('SHOW TABLES');sort($tables);$index=array();
foreach($tables AS $table) {
 if(!preg_match('/^xf_[a-zA-Z0-9_]+$/D',$table)) {throw new RuntimeException('Unexpected synthetic table');}
 $quoted='`'.$table.'`';$ddl=array_values($db->fetchRow('SHOW CREATE TABLE '.$quoted))[1];$schema[$table]=preg_replace('/ AUTO_INCREMENT=[0-9]+/','',$ddl);
 $columns=$db->fetchAll('SHOW COLUMNS FROM '.$quoted);$primary=array();foreach($db->fetchAll('SHOW INDEX FROM '.$quoted) AS $key) {if($key['Key_name']==='PRIMARY') {$primary[(int)$key['Seq_in_index']]=$key['Column_name'];}}ksort($primary);
 $rows=array();$statement=$db->query('SELECT * FROM '.$quoted);while($row=$statement->fetch()) {$encoded=array();foreach($row AS $column=>$value) {$encoded[$column]=$value===null?null:base64_encode((string)$value);}$rows[]=json_encode($encoded,JSON_THROW_ON_ERROR);}
 sort($rows,SORT_STRING);$bytes="[".implode(",\n",$rows)."]\n";file_put_contents($private.'/'.$table.'.json',$bytes);chmod($private.'/'.$table.'.json',0600);
 $index[$table]=array('rows'=>count($rows),'sha256'=>hash('sha256',$bytes),'primary'=>array_values($primary),'columns'=>array_column($columns,'Field'));
}
file_put_contents($private.'/schema.json',json_encode($schema,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));chmod($private.'/schema.json',0600);
file_put_contents($private.'/config.json',json_encode($app->config(),JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));chmod($private.'/config.json',0600);
file_put_contents($private.'/permissions.json',json_encode($permissions,JSON_PRETTY_PRINT));chmod($private.'/permissions.json',0600);
$expected=array_fill_keys(json_decode(file_get_contents('/review/snapshots/xf2-archive-files.json'),true),true);$files=array();$iterator=new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),function($entry) use($root) {$rel=substr($entry->getPathname(),strlen($root)+1);return !$entry->isLink() && !in_array(explode('/',$rel)[0],array('data','internal_data'),true);}));
foreach($iterator AS $entry) {if(!$entry->isFile()) {continue;}$relative=substr($entry->getPathname(),strlen($root)+1);if(in_array($relative,array('src/config.php','library/config.php','library/xf1-config.php'),true)) {continue;}$files[$relative]=array('sha256'=>hash_file('sha256',$entry->getPathname()),'targetArchiveFile'=>isset($expected[$relative]));}ksort($files);
file_put_contents($private.'/files.json',json_encode($files,JSON_PRETTY_PRINT));chmod($private.'/files.json',0600);
file_put_contents($private.'/index.json',json_encode($index,JSON_PRETTY_PRINT));chmod($private.'/index.json',0600);
$health=json_decode(file_get_contents($root.'/src/addons/XF/hashes.json'),true);$bad=array();foreach($health AS $path=>$hash) {if(!is_file($root.'/'.$path)||XF\Util\Hash::hashTextFile($root.'/'.$path,'sha256')!==$hash) {$bad[]=$path;}}
$functional['nativeHealthEntries']=count($health);$functional['nativeHealthMismatches']=$bad;$functional['tableCount']=count($tables);$functional['permissionChecks']=array_sum(array_map(function($set) {return count($set['global'])+count($set['node2']);},$permissions));
foreach(array('titlePreserved','postPreserved','legacyAddonDisabled','legacyQueueDropped','user1PasswordValid','user1WrongPasswordRejected','user2PasswordValid','user2WrongPasswordRejected','serializedAuthBytesPreserved','genderPreserved','occupationPreserved') AS $key) {if(!$functional[$key]) {throw new RuntimeException($key);}}
if($functional['databaseVersion']!==2031370 || $bad || $functional['errorLogRows'] || $functional['attachmentsSha256'][1]!==$fixture['attachmentSha256']) {throw new RuntimeException('Target integrity failed');}
file_put_contents('/review/evidence/path-state-'.$label.'.json',json_encode($functional,JSON_PRETTY_PRINT)."\n");echo json_encode($functional,JSON_PRETTY_PRINT),"\n";
