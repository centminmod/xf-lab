<?php
require __DIR__.'/xf1-bootstrap.php';
if(!is_file('/review/.xf1551-synthetic-lab')) {throw new RuntimeException('Dedicated lab marker required');}
$kind=$argv[1];$main=XenForo_Application::getDb();XenForo_Application::getOptions();
if($kind==='mysql8') {
 $db=Zend_Db::factory('Mysqli',array('host'=>'mysql8','username'=>'root','password'=>'root','dbname'=>'review','charset'=>'utf8'));
 $tables=XenForo_Install_Data_MySql::getTables();$db->query('DROP TABLE IF EXISTS xf_tag');$db->query($tables['xf_tag']);
 $db->insert('xf_tag',array('tag'=>'another variant','tag_url'=>'cafe-2'));
 XenForo_Application::set('db',$db);$tag='café';
} else {$db=$main;$tag='xf1551-native-save';}
$writer=XenForo_DataWriter::create('XenForo_DataWriter_Tag');$writer->set('tag',$tag);$writer->save();
$row=$db->fetchRow('SELECT * FROM xf_tag WHERE tag_id=?',$writer->get('tag_id'));
if(!$row || ($kind==='mysql8' && $row['tag_url']!=='cafe-3')) {throw new RuntimeException('Native tag save failed');}
$result=array('database'=>$kind,'nativeDataWriterSave'=>true,'savedUrl'=>$row['tag_url']);
if($kind==='mysql8') {$db->query('DROP TABLE xf_tag');}else{$db->delete('xf_tag','tag_id='.$db->quote($row['tag_id']));}
file_put_contents('/review/evidence/tag-save-'.$kind.'.json',json_encode($result,JSON_PRETTY_PRINT)."\n");
echo json_encode($result,JSON_PRETTY_PRINT),"\n";
