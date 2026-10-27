from pathlib import Path
import base64, json, re, subprocess, tarfile, zipfile

root = Path(__file__).resolve().parents[2]
lab = root / 'instances/wmtech-1551'
forum = lab / 'source-webroot'
base = ['docker', 'compose', '-p', 'xf_wmtech_1551', '-f', str(lab / 'compose.yml')]

def command(args, **kwargs):
    result = subprocess.run(base + args, capture_output=True, **kwargs)
    if result.returncode:
        raise RuntimeError((result.stdout + result.stderr).decode(errors='replace')[-4000:])
    return result.stdout

def php(service, args):
    return command(['run', '--rm', '--no-deps', '-T', '-e',
                    'XF_FORUM_ROOT=/review/source-webroot', service, 'php'] + args).decode()

def overlay(path):
    with zipfile.ZipFile(root / path) as archive:
        for name in archive.namelist():
            if name.startswith('upload/') and not name.endswith('/'):
                relative = Path(name[7:])
                assert '..' not in relative.parts and not relative.is_absolute()
                target = forum / relative
                target.parent.mkdir(parents=True, exist_ok=True)
                target.write_bytes(archive.read(name))

bootstrap = "require '/wmtech/tests/xf1-bootstrap.php';$db=XenForo_Application::getDb();"
count_code = bootstrap + """
$counts=array();foreach($db->listTables() AS $table){$counts[$table]=(int)$db->fetchOne('SELECT COUNT(*) FROM '.$db->quoteIdentifier($table));}ksort($counts);
echo json_encode(array('codeVersion'=>XenForo_Application::$versionId,'counts'=>$counts,'links'=>$db->fetchAll('SELECT user_id,provider,provider_key FROM xf_user_external_auth ORDER BY user_id,provider')));
"""
fixture_code = bootstrap + """
XenForo_Model::create('XenForo_Model_UserExternal')->rebuildExternalAuthCache(2);
$row=$db->fetchRow("SELECT * FROM xf_deferred WHERE unique_key='bridge-pending'");$data=unserialize($row['execute_data']);
$data['email']+=array('from_email'=>'sender@example.test','from_name'=>'Synthetic fixture','email_format'=>'text');$bytes=serialize($data);
$db->update('xf_deferred',array('execute_data'=>$bytes),'deferred_id='.$row['deferred_id']);
file_put_contents('/review/snapshots/bridge-before-51.sha256',hash('sha256',$bytes));
"""

assert not forum.exists(), 'This one-shot test needs a fresh owned clone'
with tarfile.open(lab / 'snapshots/baseline-files.tar') as archive:
    for member in archive.getmembers():
        assert member.name == 'webroot' or member.name.startswith('webroot/')
        assert not member.issym() and not member.islnk()
        member.name = member.name.removeprefix('webroot').lstrip('/')
        if member.name:
            archive.extract(member, forum, filter='data')
config = forum / 'library/config.php'
original, changed = re.subn(r"(\['db'\]\['dbname'\]\s*=\s*)'xenforo'", r"\1'xf_source_inventory'", config.read_text())
assert changed == 1
config.write_text(original)
command(['exec', '-T', 'db', 'mariadb', '-uroot', '-proot', '-e',
         "CREATE DATABASE xf_source_inventory; GRANT ALL ON xf_source_inventory.* TO 'xenforo'@'%';"])
sql = (lab / 'snapshots/baseline.sql').read_bytes()
for message in [b'Undefined index: fullUri', b'Wrong source version', b'The server requested authentication method unknown to the client']:
    assert message in sql, 'Historical source-harness log identity changed'
def restore():
    command(['exec', '-T', 'db', 'mariadb', '-uroot', '-proot', 'xf_source_inventory'], input=sql)
restore()
php('php72', ['-r', fixture_code])
states = {'plain': json.loads(php('php72', ['-r', count_code]))}
php('php72', ['/wmtech/tests/mail-drain.php'])
states['prepared'] = json.loads(php('php72', ['-r', count_code]))
# Replay only the XF1 stages to inventory source rows before the XF2 conversion.
restore()
config.write_text(original)
overlay('wmtech/xenforo_1.5.50_upgrade.zip')
php('php74', ['/wmtech/tests/xf1-upgrade.php', '1052470', '1055070'])
php('php74', ['-r', bootstrap + "XenForo_Model::create('XenForo_Model_Option')->updateOptions(array('facebookAppId'=>'fixture-facebook-id','facebookAppSecret'=>'fixture-facebook-secret','googleClientId'=>'fixture-google-id','googleClientSecret'=>'fixture-google-secret'));"])
php('php74', ['-r', fixture_code])
php('php74', ['/wmtech/1.5.51/tools/prepare-upgrade.php', '/review/source-webroot', '--apply'])
overlay('wmtech/xenforo_1.5.51_upgrade.zip')
php('php74', ['/wmtech/tests/xf1-upgrade.php', '1055070', '1055170'])
php('php74', ['/wmtech/tests/mail-drain.php'])
states['staged'] = json.loads(php('php74', ['-r', count_code]))
result = {'method': 'Independent source-only replay from the same private baseline; exact COUNT(*) per source table. Original endpoint snapshots remain untouched.', 'paths': {}}
for label, state in states.items():
    target = lab / ('snapshots/path-' + label)
    index = json.loads((target / 'index.json').read_text())
    links = json.loads((target / 'xf_user_connected_account.json').read_text())
    actual = sorted([{k: base64.b64decode(row[k]).decode() for k in ['user_id','provider','provider_key']} for row in links], key=lambda row: (int(row['user_id']),row['provider']))
    source_links = [{k: str(v) for k, v in row.items()} for row in state['links']]
    assert actual == source_links, label
    missing = sorted(set(state['counts']) - set(index))
    assert len(state['counts']) == 174 and len(missing) == 28
    assert state['counts']['xf_mail_queue'] == 0
    assert state['counts']['xf_error_log'] == 3, 'Unexpected source error-log count'
    result['paths'][label] = {'sourceVersion': state['codeVersion'], 'sourceTables': 174,
        'sourceRowCounts': state['counts'], 'absentAtTarget': {t: state['counts'][t] for t in missing},
        'sourceConnectedAccountKeysMatchTarget': True, 'connectedAccountRows': len(actual)}
assert states['plain']['counts']['xf_deferred'] > states['prepared']['counts']['xf_deferred']

# Exercise the new mail inventory using native serialization and a local file transport.
php('php74', ['-r', bootstrap + "$mail=new Zend_Mail('UTF-8');$mail->setFrom('sender@example.test')->addTo('recipient@example.test')->setSubject('Queue inventory probe')->setBodyText('Local synthetic mail queue probe');XenForo_Model::create('XenForo_Model_MailQueue')->insertMailQueue($mail);"])
before = php('php74', ['/wmtech/tests/read-only-digest.php'])
preflight = json.loads(php('php74', ['/wmtech/1.5.51/tools/preflight.php', '/review/source-webroot']))
after = php('php74', ['/wmtech/tests/read-only-digest.php'])
assert before == after and preflight['mailQueue']['count'] == 1 and int(preflight['mailQueue']['oldestQueueDate']) > 0
drained = json.loads(php('php74', ['-r', bootstrap + """
$config=XenForo_Application::getConfig()->toArray();$config['enableMail']=true;$config['enableMailQueue']=false;XenForo_Application::set('config',new Zend_Config($config));
$path='/review/mailqueue-probe-out';if(!is_dir($path)){mkdir($path,0700);}if(glob($path.'/*')){throw new RuntimeException('Expected an empty private output directory');}
XenForo_Mail::setupTransport(new Zend_Mail_Transport_File(array('path'=>$path)));
if(XenForo_Model::create('XenForo_Model_MailQueue')->runMailQueue(0)){throw new RuntimeException('Queue not drained');}
$files=glob($path.'/*');if(count($files)!==1 || strpos(file_get_contents($files[0]),'Local synthetic mail queue probe')===false){throw new RuntimeException('Delivery mismatch');}
XenForo_Model::create('XenForo_Model_Deferred')->runByUniqueKey('MailQueue',0,$status);
echo json_encode(array('messagesWritten'=>1,'remainingMailQueueRows'=>(int)$db->fetchOne('SELECT COUNT(*) FROM xf_mail_queue'),'externalDeliveryAttempted'=>false));
"""]))
assert drained['remainingMailQueueRows'] == 0
result['mailQueuePreflightControl'] = {'nativeQueuedMailDetected': True, 'oldestDateReported': True,
    'databaseAndConfigUnchangedByPreflight': True, **drained}
result['coverageLimits'] = ['The absent-table inventory is a source-only replay after the panel, not a fourth XF2 upgrade.',
    'Empty business tables do not validate nonempty likes, moderation, payment or history conversions.',
    'Source email/admin/style metadata counts are not a promise of XF1 template compatibility.']
out = root / 'wmtech/reports/evidence/upgrade-path-comparison/source-table-inventory.json'
out.write_text(json.dumps(result, indent=2) + '\n')
print('Source table inventory and native mail queue preflight control PASS', flush=True)
