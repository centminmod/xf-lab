<?php
require __DIR__.'/xf1-bootstrap.php';
$checks=array();
function verifySerialization($condition,$name) {
	global $checks;
	if (!$condition) { throw new RuntimeException($name); }
	$checks[$name]=true;
}
$text=array('text'=>'PHP examples ;O:8:"stdClass":0:{} ;C:3:"Foo":0:{} ;o:4:"text"', 'nested'=>array('ok'=>true));
$encoded=XenForo_Helper_Php::safeSerialize($text);
verifySerialization(XenForo_Helper_Php::safeUnserialize($encoded)===$text,'object-like string roundtrip');
verifySerialization(!XenForo_Helper_Php::serializedContainsObject($encoded),'string boundaries respected by validator');
verifySerialization(XenForo_Helper_Php::safeUnserialize('b:0;')===false && !XenForo_Helper_Php::serializedContainsObject('b:0;'),'serialized false valid');
verifySerialization(!XenForo_Helper_Php::serializedContainsObject('malformed'),'nonobject malformed data keeps validator contract');
$dw=new XenForo_DataWriter_Report();$invalid='malformed';
verifySerialization(!XenForo_DataWriter_Helper_Denormalization::verifySerialized($invalid,$dw,'content_info'),'malformed field returns validation error without object construction');
$validText=$encoded;
verifySerialization(XenForo_DataWriter_Helper_Denormalization::verifySerialized($validText,new XenForo_DataWriter_Report(),'content_info'),'object-like text accepted by field validator');
$GLOBALS['wakeupCalls']=0;
class Xf151WakeupCanary { public function __wakeup() { $GLOBALS['wakeupCalls']++; } }
class Xf151CustomCanary implements Serializable {
	public function serialize() { return 'fixture'; }
	public function unserialize($value) { $GLOBALS['wakeupCalls']++; }
}
foreach (array(new Xf151WakeupCanary(),new Xf151CustomCanary()) AS $object)
{
	$payload=serialize(array('object'=>$object));
	verifySerialization(XenForo_Helper_Php::safeUnserialize($payload)===false,'blocked '.get_class($object));
	verifySerialization(XenForo_Helper_Php::serializedContainsObject($payload),'validator blocked '.get_class($object));
}
verifySerialization($GLOBALS['wakeupCalls']===0,'no object wakeup or custom unserialize executed');
$cyclic=array();$cyclic['self']=&$cyclic;
verifySerialization(XenForo_Helper_Php::safeUnserialize(serialize($cyclic))===false,'cyclic value rejected');
$deep='leaf';for($i=0;$i<130;$i++) { $deep=array($deep); }
verifySerialization(XenForo_Helper_Php::safeUnserialize(serialize($deep))===false,'excessive depth rejected');
$nodes=array(1);for($i=0;$i<20;$i++) { $next=array();$next[0]=&$nodes;$next[1]=&$nodes;unset($nodes);$nodes=$next; }
$start=microtime(true);
verifySerialization(XenForo_Helper_Php::safeUnserialize(serialize($nodes))===false,'shared-reference expansion bounded');
verifySerialization(microtime(true)-$start<3,'bounded validation completes promptly');
echo json_encode(array('checks'=>$checks),JSON_PRETTY_PRINT),"\n";
