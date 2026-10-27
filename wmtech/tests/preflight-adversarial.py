from pathlib import Path
import subprocess,json
lab=Path(__file__).resolve().parents[2] / 'instances/wmtech-1551'
b=['docker','compose','-p','xf_wmtech_1551','-f',str(lab/'compose.yml'),'exec','-T','php74','php']
def run(args):return subprocess.run(b+args,capture_output=True,text=True,check=True).stdout
bootstrap="require '/wmtech/1.5.51/tools/bootstrap.php'; xf151_bootstrap('/review/restore-webroot'); $db=XenForo_Application::getDb(); "
setup="""$row=$db->fetchRow("SELECT * FROM xf_option WHERE data_type='array' LIMIT 1"); $row['option_id']='xf1551_preflight_probe'; $row['option_value']='not serialized'; $db->insert('xf_option',$row); $db->insert('xf_deferred',array('execute_class'=>'Xf1551_Preflight_Probe','execute_data'=>'not serialized','manual_execute'=>0,'trigger_date'=>2147483647,'unique_key'=>'xf1551_preflight_probe'));"""
cleanup="$db->delete('xf_option',\"option_id='xf1551_preflight_probe'\"); $db->delete('xf_deferred',\"unique_key='xf1551_preflight_probe'\");"
try:
 run(['-r',bootstrap+setup]);r=json.loads(run(['/wmtech/1.5.51/tools/preflight.php','/review/restore-webroot']))
 assert 'xf1551_preflight_probe' in r['invalidArrayOptionIds']
 probe=next(j for j in r['pendingJobs'] if j['class']=='Xf1551_Preflight_Probe')
 assert probe['heldCount']==1 and probe['invalidDataCount']==1 and len(probe['invalidIdSample'])==1
 result={'malformedArrayOptionReported':True,'invalidHeldJobReported':True,'heldCount':1,'invalidJobCount':1,'fixtureCleanup':True,'testedOnIndependentRestoredClone':True}
finally:run(['-r',bootstrap+cleanup])
(lab/'evidence/preflight-adversarial.json').write_text(json.dumps(result,indent=2)+'\n');print(json.dumps(result,indent=2))
