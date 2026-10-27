<?php
if(PHP_SAPI!=='cli'||!is_file('/review/.xf1551-synthetic-lab')) {throw new RuntimeException('Synthetic CLI lab required');}
require '/wmtech/1.5.51/tools/bootstrap.php';
$label=$argv[1];if(!in_array($label,array('plain','prepared','staged'),true)) {throw new RuntimeException('Unknown snapshot');}
$path='/review/snapshots/path-'.$label;$rows=json_decode(file_get_contents($path.'/xf_data_registry.json'),true);$result=array('sourceSha256'=>array(),'values'=>array());
$keys=array('autoJobRun','bannedIps','discouragedIps','options','masterStyleModifiedDate','styles');
foreach($rows AS $row) {$key=base64_decode($row['data_key'],true);if(in_array($key,$keys,true)) {$raw=base64_decode($row['data_value'],true);$value=unserialize($raw,array('allowed_classes'=>false));if(($value===false && $raw!=='b:0;') || !xf151_safe_value($value)) {throw new RuntimeException('Invalid registry snapshot');}$result['sourceSha256'][$key]=hash('sha256',$raw);$result['values'][$key]=$value;}}
file_put_contents($path.'/registry-decoded.json',json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));chmod($path.'/registry-decoded.json',0600);
echo json_encode(array('snapshot'=>$label,'decodedKeys'=>array_keys($result['values']),'valuesExported'=>false)),"\n";
