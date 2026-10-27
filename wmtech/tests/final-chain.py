from pathlib import Path
import subprocess,tarfile,zipfile,shutil,re,json,hashlib
root=Path(__file__).resolve().parents[2];lab=root/'instances/wmtech-1551';snap=lab/'snapshots';ev=lab/'evidence';forum=lab/'final-webroot'
b=['docker','compose','-p','xf_wmtech_1551','-f',str(lab/'compose.yml')]
def cmd(args,**kw):return subprocess.run(b+args,capture_output=True,check=True,**kw).stdout
def php(service,args):return cmd(['run','--rm','--no-deps','-T','-e','XF_FORUM_ROOT=/review/final-webroot','-e','XF_SOURCE_STAGE=final-1.5.51','-e','XF_RESULT_PREFIX=final-',service,'php']+args).decode()
def save(name,out):
 (ev/name).write_text(out)
 print(name,'PASS',flush=True)
def overlay(archive):
 with zipfile.ZipFile(root/archive) as z:
  for n in z.namelist():
   if n.startswith('upload/') and not n.endswith('/'):
    p=forum/n[7:];p.parent.mkdir(parents=True,exist_ok=True);p.write_bytes(z.read(n))
assert not forum.exists()
with tarfile.open(snap/'baseline-files.tar') as tar:
 for m in tar.getmembers():
  assert m.name=='webroot' or m.name.startswith('webroot/')
  assert not m.issym() and not m.islnk()
  m.name=m.name.removeprefix('webroot').lstrip('/')
  if m.name:tar.extract(m,forum,filter='data')
p=forum/'library/config.php';s=p.read_text();s,n=re.subn(r"(\['db'\]\['dbname'\]\s*=\s*)'xenforo'",r"\1'xf_final'",s);assert n==1;p.write_text(s)
cmd(['exec','-T','db','mariadb','-uroot','-proot','-e',"CREATE DATABASE xf_final; GRANT ALL ON xf_final.* TO 'xenforo'@'%';"])
cmd(['exec','-T','db','mariadb','-uroot','-proot','xf_final'],input=(snap/'baseline.sql').read_bytes())
save('state-final-1.5.24.json',php('php72',['/wmtech/tests/xf1-state.php','final-1.5.24']))
overlay('wmtech/xenforo_1.5.50_upgrade.zip')
save('native-final-upgrade-1.5.50.txt',php('php74',['/wmtech/tests/xf1-upgrade.php','1052470','1055070']))
save('state-final-1.5.50.json',php('php74',['/wmtech/tests/xf1-state.php','final-1.5.50']))
# Complete the original synthetic mail envelope and recover known dummy provider settings privately.
code='''require '/wmtech/tests/xf1-bootstrap.php'; $db=XenForo_Application::getDb(); XenForo_Model::create('XenForo_Model_Option')->updateOptions(array('facebookAppId'=>'fixture-facebook-id','facebookAppSecret'=>'fixture-facebook-secret','googleClientId'=>'fixture-google-id','googleClientSecret'=>'fixture-google-secret')); XenForo_Model::create('XenForo_Model_UserExternal')->rebuildExternalAuthCache(2); $row=$db->fetchRow("SELECT * FROM xf_deferred WHERE unique_key='bridge-pending'");$data=unserialize($row['execute_data'],array('allowed_classes'=>false));$data['email']+=array('from_email'=>'sender@example.test','from_name'=>'Synthetic fixture','email_format'=>'text');$bytes=serialize($data);$db->update('xf_deferred',array('execute_data'=>$bytes),'deferred_id='.$row['deferred_id']);file_put_contents('/review/snapshots/bridge-before-51.sha256',hash('sha256',$bytes));echo "PRIVATE_FIXTURE_COMPLETED\\n";'''
php('php74',['-r',code])
save('prepare-final-1.5.50.json',php('php74',['/wmtech/1.5.51/tools/prepare-upgrade.php','/review/final-webroot','--apply']))
overlay('wmtech/xenforo_1.5.51_upgrade.zip')
save('native-final-upgrade-1.5.51.txt',php('php74',['/wmtech/tests/xf1-upgrade.php','1055070','1055170']))
save('http-options-final.json',php('php74',['/wmtech/tests/http-options.php']))
save('obsolete-cleanup-final.json',php('php74',['/wmtech/1.5.51/tools/remove-obsolete-files.php','/review/final-webroot','--apply']))
save('native-health-final-php74.json',php('php74',['/wmtech/tests/native-health.php']))
save('business-job-drain-final.json',php('php74',['/wmtech/tests/mail-drain.php']))
s= (forum/'library/config.php').read_text()+"\n$config['cookie']['sameSite']='Strict';\n$config['cookie']['samesite']='Lax';\n";(forum/'library/config.php').write_text(s)
save('state-final-1.5.51.json',php('php74',['/wmtech/tests/xf1-state.php','final-1.5.51']))
save('preflight-final-1.5.51.json',php('php74',['/wmtech/1.5.51/tools/preflight.php','/review/final-webroot']))
save('lint-final-php74.json',php('php74',['/wmtech/tests/lint.php','/review/final-webroot']))
rel=json.loads(zipfile.ZipFile(root/'wmtech/xenforo_1.5.51_upgrade.zip').read('RELEASE.json'));(ev/'final-payload.json').write_text(json.dumps(rel,indent=2))
print('FINAL_51_READY',rel['uploadTreeSha256'],flush=True)
overlay('archives/xenforo-2.3.13.zip')
save('config-final-2.3.13.json',php('php83',['/wmtech/tests/xf2-config.php']))
r=subprocess.run(['python3',str(root/'wmtech/tests/native-xf2.py'),'final'],cwd=root,capture_output=True,text=True,check=True);print(r.stdout,flush=True)
save('state-final-2.3.13.json',php('php83',['/wmtech/tests/xf2-state.php']))
print('FINAL_FULL_CHAIN_PASS',flush=True)
