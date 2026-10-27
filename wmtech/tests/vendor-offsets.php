<?php
require __DIR__.'/xf1-bootstrap.php';
$payload=array('unicode'=>'café','nested'=>array(1,true,null),'escape'=>"a\\b\n\"");
$json=Zend_Json_Encoder::encode($payload);
if(Zend_Json_Decoder::decode($json,Zend_Json::TYPE_ARRAY)!==$payload) { throw new RuntimeException('Zend JSON roundtrip'); }
$isbn=new Zend_Validate_Isbn();
if(!$isbn->isValid('9780306406157') || !$isbn->isValid('0306406152') || $isbn->isValid('9780000000000')) { throw new RuntimeException('ISBN validation'); }
echo "PASS manual Zend JSON and ISBN offset paths\n";
