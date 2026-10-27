from pathlib import Path
import json,subprocess
root=Path(__file__).resolve().parents[2];lab=root/'instances/wmtech-1551';fixture=lab/'cleanup-boundary';assert not fixture.exists()
for name in ['library/XenForo/Application.php','src/XF.php','fb_channel.php','js/videojs/video-js.swf','js/swfupload/old.swf','library/Zend/Service/WindowsAzure/old.php']:
 p=fixture/name;p.parent.mkdir(parents=True,exist_ok=True);p.write_text('synthetic fixture')
b=['docker','compose','-p','xf_wmtech_1551','-f',str(lab/'compose.yml'),'exec','-T','php83','php','/wmtech/1.5.51/tools/remove-obsolete-files.php']
def run(*args):return subprocess.run(b+list(args),capture_output=True,text=True)
before={str(p.relative_to(fixture)):p.read_bytes() for p in fixture.rglob('*') if p.is_file()}
r=run('/review/cleanup-boundary','--apply');assert r.returncode==1 and 'XF2 files detected' in r.stderr
assert before=={str(p.relative_to(fixture)):p.read_bytes() for p in fixture.rglob('*') if p.is_file()}
(fixture/'src/XF.php').unlink();r=run('/review/cleanup-boundary','--apply');assert r.returncode==0,r.stderr
assert not (fixture/'fb_channel.php').exists() and (fixture/'library/XenForo/Application.php').exists()
r=run('/review/final-webroot');assert r.returncode==1 and 'XF2 files detected' in r.stderr
result={'xf2FixtureApplyRefusedBeforeAnyDeletion':True,'xf1FixtureAllowlistedCleanupPassed':True,'actualConvertedXf2RootRefused':True,'reason':'XF1 Application.php can remain after an XF2 overlay; its presence alone does not prove an XF1 tree','targetArchiveReintroducesFourUnhashedFlashAssetFiles':True,'actualXf2FilesDeleted':False}
(lab/'evidence/cleanup-version-boundary.json').write_text(json.dumps(result,indent=2)+'\n');print(json.dumps(result,indent=2))
