from pathlib import Path
import subprocess,tarfile,hashlib,json,re,os
root=Path(__file__).resolve().parents[2];lab=root/'instances/wmtech-1551';snap=lab/'snapshots';ev=lab/'evidence'
c=['docker','compose','-p','xf_wmtech_1551','-f',str(lab/'compose.yml')]
def run(args,**kwargs):return subprocess.run(c+args,check=True,capture_output=True,**kwargs)
# Set an explicit target spelling, preserving the legacy policy as a precedence control.
for forum in ['webroot','direct-webroot']:
 p=lab/forum/'library/config.php';s=p.read_text()
 if 'XF1551_COOKIE_MAPPING_FIXTURE' not in s:
  s+="\n// XF1551_COOKIE_MAPPING_FIXTURE\n$config['cookie']['sameSite'] = 'Strict';\n$config['cookie']['samesite'] = 'Lax';\n"
  p.write_text(s)
run(['stop','php72','php74'])
dump=run(['exec','-T','db','mariadb-dump','-uroot','-proot','--single-transaction','--skip-comments','xenforo']).stdout
(snap/'verified-1.5.51.sql').write_bytes(dump);(snap/'verified-1.5.51.sql').chmod(0o600)
with tarfile.open(snap/'verified-1.5.51-files.tar','w') as tar:tar.add(lab/'webroot',arcname='forum')
(snap/'verified-1.5.51-files.tar').chmod(0o600)
restore=lab/'restore-webroot';assert not restore.exists()
# Archive is produced immediately above from the owned synthetic webroot.
with tarfile.open(snap/'verified-1.5.51-files.tar') as tar:
 for member in tar.getmembers():
  assert member.name=='forum' or member.name.startswith('forum/')
  assert not member.issym() and not member.islnk()
  member.name=member.name.removeprefix('forum').lstrip('/')
  if member.name:tar.extract(member,restore,filter='data')
p=restore/'library/config.php';s=p.read_text()
s,n=re.subn(r"(\['db'\]\['dbname'\]\s*=\s*)'xenforo'",r"\1'xf_restore'",s);assert n==1
p.write_text(s)
run(['exec','-T','db','mariadb','-uroot','-proot','-e',"CREATE DATABASE xf_restore; GRANT ALL ON xf_restore.* TO 'xenforo'@'%';"])
run(['exec','-T','db','mariadb','-uroot','-proot','xf_restore'],input=dump)
run(['start','php74'])
r=run(['exec','-T','-e','XF_FORUM_ROOT=/review/restore-webroot','php74','php','/wmtech/tests/xf1-state.php','restored-1.5.51']);state=json.loads(r.stdout)
orig=json.loads((ev/'state-1.5.51.json').read_text())
fields=['codeVersion','databaseVersion','users','threads','posts','attachmentCount','attachmentPreserved','titlePreserved','adminPasswordValid','legacyPasswordValid','wrongPasswordRejected','providers','pendingBridgeJobs','schemaMatches-1.5.24','authStorageMatches-1.5.24','signaturesMatch-1.5.50']
assert all(state[k]==orig[k] for k in fields)
result={'matchedDatabaseAndFiles':True,'restoredIndependentDatabaseAndRoot':True,'restoredStateMatchesSource':True,'checkedFields':fields,'databaseBackupSha256':hashlib.sha256(dump).hexdigest(),'filesBackupSha256':hashlib.file_digest((snap/'verified-1.5.51-files.tar').open('rb'),'sha256').hexdigest(),'containsOnlySyntheticFixtures':True,'sourceWritersStoppedDuringBackup':True,'configMapping':'legacy Strict, explicit lowercase Lax; lowercase must win'}
(ev/'backup-restore-1.5.51.json').write_text(json.dumps(result,indent=2)+'\n');print(json.dumps(result,indent=2))
