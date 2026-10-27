from pathlib import Path
import hashlib
import json
import os
import shutil
import subprocess

root = Path(__file__).resolve().parents[2]
lab = root / 'instances/wmtech-1551'
compose = ['docker','compose','-p','xf_wmtech_1551','-f',str(lab/'compose.yml'),'exec','-T','php74','php']

def command(*args, ok=True):
    result = subprocess.run(compose+list(args),capture_output=True,text=True)
    if ok and result.returncode:
        raise RuntimeError(result.stderr + result.stdout)
    return result

before = json.loads(command('/wmtech/tests/read-only-digest.php').stdout)
preflight = command('/wmtech/1.5.51/tools/preflight.php','/var/www/html').stdout
metadata = json.loads(preflight)
after = json.loads(command('/wmtech/tests/read-only-digest.php').stdout)
assert before == after
assert all(secret not in preflight for secret in ['fixture-facebook-secret','fixture-google-secret','43314b267b1dae1164f4c038a1d7cbcb'])
(lab/'evidence/preflight-1.5.51.json').write_text(preflight)
checks = {'preflightDatabaseUnchanged':True,'preflightConfigUnchanged':True,'preflightSecretsNotPrinted':True}

work = lab/'io-fixtures'
work.mkdir(exist_ok=True)
forum = work/'prepare'
shutil.copytree(lab/'webroot/library',forum/'library',dirs_exist_ok=True)
source = (lab/'snapshots/config-before-prepare.php').read_bytes()
for name, contents in [('implicit',source),('closing-tag',source+b'?>\n'),('custom',source+b"\n$config['globalSalt'] = 'private-fixture-custom-salt';\n"),('config-function',source+b"\nfunction xf151FixtureSalt() { return 'private-fixture-custom-salt'; }\n$config['globalSalt'] = xf151FixtureSalt();\n")]:
    path=forum/'library/config.php'
    path.write_bytes(contents);path.chmod(0o640)
    stat=path.stat()
    cli_root='/review/io-fixtures/prepare'
    dry=json.loads(command('/wmtech/1.5.51/tools/prepare-upgrade.php',cli_root).stdout)
    assert path.read_bytes()==contents
    applied=command('/wmtech/1.5.51/tools/prepare-upgrade.php',cli_root,'--apply')
    assert 'private-fixture-custom-salt' not in applied.stdout
    assert json.loads(applied.stdout)['action']=='ready'
    prepared=path.read_bytes();new_stat=path.stat()
    assert (stat.st_uid,stat.st_gid,stat.st_mode&0o777)==(new_stat.st_uid,new_stat.st_gid,new_stat.st_mode&0o777)
    assert not list(path.parent.glob('.xf151-*'))
    command('/wmtech/1.5.51/tools/prepare-upgrade.php',cli_root,'--apply')
    assert path.read_bytes()==prepared
    if name in ('custom','config-function'): assert prepared==contents
    checks['prepare-'+name+'-dry-run-atomic-idempotent']=True

cleanup=work/'cleanup'
(cleanup/'library/XenForo').mkdir(parents=True,exist_ok=True)
(cleanup/'library/XenForo/Application.php').write_text('<?php\n')
(cleanup/'data').mkdir(exist_ok=True)
(cleanup/'data/protected').write_text('retain')
for relative in ['fb_channel.php','library/Zend/Service/WindowsAzure/obsolete.php','js/swfupload/obsolete.swf','js/videojs/video-js.swf']:
    path=cleanup/relative;path.parent.mkdir(parents=True,exist_ok=True);path.write_text('obsolete')
cli_root='/review/io-fixtures/cleanup'
command('/wmtech/1.5.51/tools/remove-obsolete-files.php',cli_root)
assert (cleanup/'fb_channel.php').exists()
command('/wmtech/1.5.51/tools/remove-obsolete-files.php',cli_root,'--apply')
assert (cleanup/'data/protected').read_text()=='retain'
assert not (cleanup/'library/Zend/Service/WindowsAzure').exists()
checks['cleanup-default-read-only-and-allowlist']=True
outside=work/'outside';outside.mkdir(exist_ok=True);(outside/'protected').write_text('outside')
shutil.rmtree(cleanup/'library/Zend')
(cleanup/'library/Zend').symlink_to(outside.resolve(),target_is_directory=True)
(cleanup/'fb_channel.php').write_text('retain-on-refusal')
result=command('/wmtech/1.5.51/tools/remove-obsolete-files.php',cli_root,'--apply',ok=False)
assert result.returncode and (outside/'protected').read_text()=='outside' and (cleanup/'fb_channel.php').read_text()=='retain-on-refusal'
checks['cleanup-refuses-linked-ancestor-before-any-delete']=True
(lab/'evidence/io-checks.json').write_text(json.dumps(checks,indent=2)+'\n')
print(json.dumps(checks,indent=2))
