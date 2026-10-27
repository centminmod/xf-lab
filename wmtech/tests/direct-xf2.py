from pathlib import Path
import hashlib,json,re,subprocess,sys,tarfile,zipfile
root=Path(__file__).resolve().parents[2];lab=root/'instances/wmtech-1551';snap=lab/'snapshots';ev=lab/'evidence'
label=sys.argv[1];assert label in {'plain','prepared'};forum=lab/(label+'-webroot');database='xf_'+label
b=['docker','compose','-p','xf_wmtech_1551','-f',str(lab/'compose.yml')]
def command(args,**kw):
 result=subprocess.run(b+args,capture_output=True,**kw)
 if result.returncode:raise RuntimeError((result.stdout+result.stderr).decode(errors='replace')[-6000:])
 return result.stdout
def php(service,args):return command(['run','--rm','--no-deps','-T','-e','XF_FORUM_ROOT=/review/'+label+'-webroot',service,'php']+args).decode()
def save(name,output):
 (ev/name).write_text(output);print(name,'PASS',flush=True)
assert not forum.exists()
with tarfile.open(snap/'baseline-files.tar') as tar:
 for member in tar.getmembers():
  assert member.name=='webroot' or member.name.startswith('webroot/')
  assert not member.issym() and not member.islnk()
  member.name=member.name.removeprefix('webroot').lstrip('/')
  if member.name:tar.extract(member,forum,filter='data')
p=forum/'library/config.php';text=p.read_text();text,count=re.subn(r"(\['db'\]\['dbname'\]\s*=\s*)'xenforo'",r"\1'"+database+"'",text);assert count==1;p.write_text(text)
command(['exec','-T','db','mariadb','-uroot','-proot','-e',"CREATE DATABASE "+database+"; GRANT ALL ON "+database+".* TO 'xenforo'@'%';"])
command(['exec','-T','db','mariadb','-uroot','-proot',database],input=(snap/'baseline.sql').read_bytes())
# Complete the same synthetic fixture fields used by the staged replay.
fixture="""require '/wmtech/tests/xf1-bootstrap.php';$db=XenForo_Application::getDb();XenForo_Model::create('XenForo_Model_UserExternal')->rebuildExternalAuthCache(2);$row=$db->fetchRow("SELECT * FROM xf_deferred WHERE unique_key='bridge-pending'");$data=unserialize($row['execute_data']);$data['email']+=array('from_email'=>'sender@example.test','from_name'=>'Synthetic fixture','email_format'=>'text');$bytes=serialize($data);$db->update('xf_deferred',array('execute_data'=>$bytes),'deferred_id='.$row['deferred_id']);echo json_encode(array('fixtureEnvelopeCompleted'=>true,'providerCacheRebuilt'=>true,'pendingBusinessJobs'=>1));"""
save('fixture-'+label+'.json',php('php72',['-r',fixture]))
if label=='prepared':
 save('prepare-'+label+'.json',php('php72',['/wmtech/1.5.51/tools/prepare-upgrade.php','/review/'+label+'-webroot','--apply']))
 p=forum/'library/config.php';p.write_text(p.read_text()+"\n$config['cookie']['sameSite']='Strict';\n$config['cookie']['samesite']='Lax';\n")
 setup="""require '/wmtech/tests/xf1-bootstrap.php';XenForo_Model::create('XenForo_Model_Option')->updateOptions(array('boardUrl'=>'http://localhost:8774'));$bytes=XenForo_Application::getDb()->fetchOne("SELECT execute_data FROM xf_deferred WHERE unique_key='bridge-pending'");file_put_contents('/review/snapshots/bridge-before-51.sha256',hash('sha256',$bytes));"""
 php('php72',['-r',setup]);out=json.loads(php('php72',['/wmtech/tests/mail-drain.php']));out['payloadCheckedBeforeDrain']=out.pop('payloadBytesPreservedAcrossUpgrade');save('business-job-drain-'+label+'.json',json.dumps(out,indent=2)+'\n')
save('state-'+label+'-1.5.24.json',php('php72',['/wmtech/tests/xf1-state.php',label+'-1.5.24']))
with zipfile.ZipFile(root/'archives/xenforo-2.3.13.zip') as archive:
 for name in archive.namelist():
  if name.startswith('upload/') and not name.endswith('/'):
   relative=Path(name[7:]);assert '..' not in relative.parts and not relative.is_absolute();p=forum/relative;p.parent.mkdir(parents=True,exist_ok=True);p.write_bytes(archive.read(name))
save('config-'+label+'-2.3.13.json',php('php83',['/wmtech/tests/path-config.php']))
r=subprocess.run(['python3',str(root/'wmtech/tests/native-xf2.py'),label],cwd=root,capture_output=True,text=True,check=True);print(r.stdout,flush=True)
provenance={'path':label,'sourceDatabaseSha256':hashlib.file_digest((snap/'baseline.sql').open('rb'),'sha256').hexdigest(),'sourceFilesSha256':hashlib.file_digest((snap/'baseline-files.tar').open('rb'),'sha256').hexdigest(),'targetArchiveSha256':hashlib.file_digest((root/'archives/xenforo-2.3.13.zip').open('rb'),'sha256').hexdigest(),'intermediateXf1Upgrades':False,'sharedFixtureCompletion':True,'explicitOperationalPreparation':label=='prepared'}
save('path-provenance-'+label+'.json',json.dumps(provenance,indent=2)+'\n')
save('path-state-'+label+'.json',php('php83',['/wmtech/tests/path-state.php',label]))
