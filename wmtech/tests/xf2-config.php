<?php
if (PHP_SAPI !== 'cli' || !is_file('/review/.xf1551-synthetic-lab')) { throw new RuntimeException('Synthetic CLI lab required'); }
$root=getenv('XF_FORUM_ROOT') ?: '/var/www/html';chdir($root);
require $root.'/src/XF.php';XF::start($root);$app=XF::setupApp('XF\Install\App');
$upgrader=new XF\Install\Upgrader($app);$mapped=$upgrader->migrateLegacyConfigIfNeeded($written);
$old=json_decode(file_get_contents('/review/snapshots/state-'.(getenv('XF_SOURCE_STAGE') ?: (getenv('XF_FORUM_ROOT')?'direct-1.5.51':'1.5.51')).'.json'),true);
$fixture=$upgrader->translateLegacyConfig(['db'=>['adapter'=>'mysqli','adapterNamespace'=>'Zend_Db','dbname'=>'fixture'],'cookie'=>['sameSite'=>'Strict','samesite'=>'Lax'],'rebuildMaxExecution'=>7,'untrustedHttpClient'=>['adapter'=>'fixture'],'paypalSslVerifyPeer'=>true,'unknownProbeKey'=>'fixture']);
$result=['nativeConfigWritten'=>$written,'saltPreserved'=>($mapped['globalSalt']??null)===$old['salt'],'lowercasePolicyRetained'=>($mapped['cookie']['samesite']??null)==='Lax','legacyAliasRetainedAsExtraKey'=>($mapped['cookie']['sameSite']??null)==='Strict','transportAndUnknownKeysDropped'=>!isset($fixture['untrustedHttpClient'])&&!isset($fixture['paypalSslVerifyPeer'])&&!isset($fixture['unknownProbeKey']),'jobRuntimeMapped'=>($fixture['jobMaxRunTime']??null)===7,'dbAdapterRemoved'=>!isset($fixture['db']['adapter'])&&!isset($fixture['db']['adapterNamespace'])];
foreach($result AS $key=>$ok) {if(!$ok) {throw new RuntimeException($key);}}
echo json_encode($result,JSON_PRETTY_PRINT),"\n";
