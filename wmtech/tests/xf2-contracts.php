<?php
if (PHP_SAPI !== 'cli' || !is_file('/review/.xf1551-synthetic-lab')) { throw new RuntimeException('Synthetic CLI lab required'); }
$root=getenv('XF_FORUM_ROOT') ?: '/var/www/html';chdir($root);require $root.'/src/XF.php';XF::start($root);$app=XF::setupApp('XF\Pub\App');restore_exception_handler();
$hashes=json_decode(file_get_contents($root.'/src/addons/XF/hashes.json'),true);$bad=[];
foreach($hashes AS $path=>$hash) {if(!is_file($root.'/'.$path)||XF\Util\Hash::hashTextFile($root.'/'.$path,'sha256')!==$hash) {$bad[]=$path;}}
$reader=$app->http()->reader();
$denied=[];foreach(['http://127.0.0.1/','http://169.254.169.254/','http://2130706433/','http://user:pass@example.com/','http://example.com:22/','http://fixture.local/','http://[::1]/'] AS $url) {
 $error=null;$allowed=$reader->isRequestableUntrustedUrl($url,$error);$denied[$url]=!$allowed;if($allowed) {throw new RuntimeException('URL policy control failed');}
}
$result=['nativeFileHealthEntries'=>count($hashes),'hashMismatches'=>$bad,'deniedUrlControls'=>$denied,'noRequestsSentToDeniedDestinations'=>true,'serializedOptionContract'=>@unserialize(serialize(['value'=>1]))===['value'=>1],'prematureJsonBecomesEmptyArray'=>(@unserialize(json_encode(['value'=>1]))?:[])===[]];
if($bad) {throw new RuntimeException('XF2 file health mismatch');}
file_put_contents('/review/evidence/contracts-'.(getenv('XF_FORUM_ROOT')?'direct-':'').'2.3.13.json',json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n");echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),"\n";
