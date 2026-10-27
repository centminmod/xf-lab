<?php
if (PHP_SAPI !== 'cli' || !is_file('/review/.xf1551-synthetic-lab')) { throw new RuntimeException('Synthetic CLI lab required'); }
$root=getenv('XF_FORUM_ROOT');if(!in_array($root,array('/review/plain-webroot','/review/prepared-webroot'),true)) {throw new RuntimeException('Unexpected comparison root');}
chdir($root);require $root.'/src/XF.php';XF::start($root);$app=XF::setupApp('XF\Install\App');
$upgrader=new XF\Install\Upgrader($app);$mapped=$upgrader->migrateLegacyConfigIfNeeded($written);
if(!$written) {throw new RuntimeException('Native config migration failed');}
$result=array('nativeConfigWritten'=>true,'explicitSaltCopied'=>isset($mapped['globalSalt']),
 'lowercaseCookiePolicyExplicit'=>isset($mapped['cookie']['samesite']),'legacyCookieAliasExplicit'=>isset($mapped['cookie']['sameSite']));
echo json_encode($result,JSON_PRETTY_PRINT),"\n";
