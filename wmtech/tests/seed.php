<?php
require __DIR__.'/xf1-bootstrap.php';
$db=XenForo_Application::getDb();
if ((int)$db->fetchOne("SELECT option_value FROM xf_option WHERE option_id='currentVersionId'")!==1052470) {throw new RuntimeException('Wrong source version');}
$install=XenForo_Model::create('XenForo_Install_Model_Install');
$install->writeInstallLock();$install->createDirectories();
XenForo_Visitor::setup(1);
$user=XenForo_DataWriter::create('XenForo_DataWriter_User');
$user->bulkSet(array('username'=>'legacy_user','email'=>'legacy@example.test','user_group_id'=>2,'user_state'=>'valid','gender'=>'male','occupation'=>'Migration engineer'));
$user->setPassword('LegacyUserPass123');$user->save();
$uid=$user->get('user_id');
$core=new XenForo_Authentication_Core();
$db->update('xf_user_authenticate',array('scheme_class'=>'XenForo_Authentication_Core','data'=>$core->generate('LegacyUserPass123')),'user_id='.$uid);
$thread=XenForo_DataWriter::create('XenForo_DataWriter_Discussion_Thread');
$thread->bulkSet(array('node_id'=>2,'title'=>'Migration fixture & UTF-8 café','user_id'=>1,'username'=>'admin','discussion_state'=>'visible'));
$thread->getFirstMessageDw()->set('message','Stable content [b]bold[/b] with café.');$thread->save();
$postId=$thread->get('first_post_id');
$data=XenForo_DataWriter::create('XenForo_DataWriter_AttachmentData');
$data->bulkSet(array('user_id'=>1,'filename'=>'migration.txt'));
$data->setExtraData(XenForo_DataWriter_AttachmentData::DATA_FILE_DATA,"Migration attachment fixture\n");$data->save();
$attach=XenForo_DataWriter::create('XenForo_DataWriter_Attachment');
$attach->bulkSet(array('data_id'=>$data->get('data_id'),'content_type'=>'post','content_id'=>$postId,'unassociated'=>0));$attach->save();
$db->update('xf_post',array('attach_count'=>1),'post_id='.$postId);
$db->insert('xf_addon',array('addon_id'=>'BridgeFixture','title'=>'Migration fixture add-on (no code)','version_string'=>'1.0','version_id'=>1000070,'active'=>1,'install_callback_class'=>'','install_callback_method'=>'','uninstall_callback_class'=>'','uninstall_callback_method'=>'','url'=>''));
XenForo_Application::$allowManualDeferred=true;
XenForo_Application::defer('UserEmail',array('email'=>array('email_title'=>'Pending job fixture','email_body'=>'PHP example ;O:8:"stdClass":0:{}; drain before cutover'),'criteria'=>array()),'bridge-pending',true);
$path=XenForo_Model::create('XenForo_Model_Attachment')->getAttachmentDataFilePath($data->getMergedData());
XenForo_Model::create('XenForo_Model_Option')->updateOptions(array('facebookAppId'=>'fixture-facebook-id','facebookAppSecret'=>'fixture-facebook-secret','googleClientId'=>'fixture-google-id','googleClientSecret'=>'fixture-google-secret','boardUrl'=>'http://localhost:8772'));
foreach (array('facebook','google') AS $provider) {
 $db->insert('xf_user_external_auth',array('user_id'=>$uid,'provider'=>$provider,'provider_key'=>'fixture-'.$provider.'-user','extra_data'=>serialize(array())));
}
$fixture=array('legacyUserId'=>$uid,'threadId'=>$thread->get('thread_id'),'postId'=>$postId,'attachmentId'=>$attach->get('attachment_id'),'attachmentDataId'=>$data->get('data_id'),'attachmentSha256'=>hash_file('sha256',$path),'attachmentPath'=>$path,'users'=>$db->fetchOne('SELECT COUNT(*) FROM xf_user'),'threads'=>$db->fetchOne('SELECT COUNT(*) FROM xf_thread'),'posts'=>$db->fetchOne('SELECT COUNT(*) FROM xf_post'),'attachmentCount'=>$db->fetchOne('SELECT COUNT(*) FROM xf_attachment'),'pendingBridgeJobs'=>$db->fetchOne("SELECT COUNT(*) FROM xf_deferred WHERE unique_key='bridge-pending'"),'sourceVersion'=>XenForo_Application::$versionId);
if ($fixture['pendingBridgeJobs']!=1 || !$fixture['attachmentSha256']) {throw new RuntimeException('Fixture self-check failed');}
file_put_contents('/review/evidence/source-fixture.json',json_encode($fixture,JSON_PRETTY_PRINT));
echo json_encode($fixture,JSON_PRETTY_PRINT),"\n";
