from pathlib import Path
import base64,hashlib,json
root=Path(__file__).resolve().parents[2];lab=root/'instances/wmtech-1551';snap=lab/'snapshots'
TIME='@generated-time';HISTORY={1055070,1055170}
def value(x):return None if x is None else base64.b64decode(x).decode()
def rows(folder,table):return json.loads((folder/(table+'.json')).read_text())
def canonical(data):return sorted(json.dumps(r,sort_keys=True,separators=(',',':')) for r in data)
def normalized(folder,table):
 result=rows(folder,table)
 if table=='xf_phrase':
  keys=[(r['language_id'],r['title']) for r in result];assert len(keys)==len(set(keys))
  for r in result:r.pop('phrase_id')
 elif table=='xf_phrase_map':
  phrases={r['phrase_id']:[r['language_id'],r['title']] for r in rows(folder,'xf_phrase')}
  for r in result:r['phrase_id']=phrases[r['phrase_id']] if value(r['phrase_id'])!='0' else '@no-phrase'
 elif table=='xf_upgrade_log':
  result=[r for r in result if int(value(r['version_id'])) not in HISTORY]
  for r in result:r['completion_date']=TIME
 elif table in {'xf_cron_entry','xf_job','xf_style','xf_template'}:
  column={'xf_cron_entry':'next_run','xf_job':'trigger_date','xf_style':'last_modified_date','xf_template':'last_edit_date'}[table]
  for r in result:r[column]=TIME
 elif table=='xf_option':
  for r in result:
   if value(r['option_id']) in {'jsLastUpdate','iconSpriteLastUpdate'}:r['option_value']=TIME
 elif table=='xf_data_registry':
  extract=json.loads((folder/'registry-decoded.json').read_text());decoded=extract['values']
  decoded['autoJobRun']=TIME;decoded['masterStyleModifiedDate']=TIME
  for key in ['bannedIps','discouragedIps']:decoded[key]['version']=TIME
  for key in ['jsLastUpdate','iconSpriteLastUpdate']:decoded['options'][key]=TIME
  for style in decoded['styles'].values():style['last_modified_date']=TIME
  for r in result:
   key=value(r['data_key'])
   if key in decoded:
    assert hashlib.sha256(base64.b64decode(r['data_value'])).hexdigest()==extract['sourceSha256'][key],'Stale registry decode'
    r['data_value']=decoded[key]
 return canonical(result)
result={}
for label in ['plain','prepared']:
 a=snap/('path-'+label);b=snap/'path-staged';ai=json.loads((a/'index.json').read_text());bi=json.loads((b/'index.json').read_text());assert set(ai)==set(bi)
 differences=[table for table in sorted(ai) if normalized(a,table)!=normalized(b,table)]
 result[label]={'tablesCompared':len(ai),'tablesEqualAfterDeclaredNormalization':len(ai)-len(differences),'remainingDifferentTables':differences,'effectivePermissionsEqual':json.loads((a/'permissions.json').read_text())==json.loads((b/'permissions.json').read_text())}
assert result['prepared']['remainingDifferentTables']==[],result
assert result['plain']['remainingDifferentTables']==['xf_data_registry','xf_option'],result
result['normalizationLedger']={'allocatedPhraseIds':'Compare all phrase fields except phrase_id, using language_id/title. Replace phrase_map references with the referenced phrase language_id/title; every nonzero reference must resolve.','wallClockOrGeneratedVersionFields':['xf_cron_entry.next_run','xf_job.trigger_date','xf_style.last_modified_date','xf_template.last_edit_date','xf_upgrade_log.completion_date','xf_option.jsLastUpdate','xf_option.iconSpriteLastUpdate','registry.autoJobRun','registry.masterStyleModifiedDate','registry.bannedIps.version','registry.discouragedIps.version','registry.options.jsLastUpdate','registry.options.iconSpriteLastUpdate','registry.styles.*.last_modified_date'],'routeHistoryEntries':sorted(HISTORY),'neverNormalizedAway':['content IDs and bytes','authentication bytes','provider settings and links','permission values','cron rules','job class/data/manual flags','effective globalSalt differences'],'plainRemainingDifference':'Only boardUrl, mirrored in the registry options cache. The XF1 upgrade harness changed its fixture URL to port 8774; the plain direct clone retained the original port 8772.'}
# Negative controls change private in-memory rows; the real snapshots stay untouched.
original_rows=rows
controls={}
for table,column in [('xf_user','email'),('xf_user_authenticate','data'),('xf_connected_account_provider','options'),('xf_phrase','phrase_text'),('xf_cron_entry','run_rules')]:
 def probe(folder,name,table=table,column=column):
  data=original_rows(folder,name)
  if folder==snap/'path-prepared' and name==table:data[0][column]=base64.b64encode(b'changed synthetic comparison control').decode()
  return data
 rows=probe
 controls[table+'.'+column]=normalized(snap/'path-prepared',table)!=normalized(snap/'path-staged',table)
 rows=original_rows
def stale_registry_probe(folder,name):
 data=original_rows(folder,name)
 if folder==snap/'path-prepared' and name=='xf_data_registry':
  next(r for r in data if value(r['data_key'])=='options')['data_value']=base64.b64encode(b'changed registry control').decode()
 return data
rows=stale_registry_probe
try:
 normalized(snap/'path-prepared','xf_data_registry')
 controls['staleRegistryDecodeRefused']=False
except AssertionError:
 controls['staleRegistryDecodeRefused']=True
finally:
 rows=original_rows
assert all(controls.values()),controls
result['negativeControlsDetected']=controls
result['privateValuesExported']=False
(lab/'evidence/path-semantic-comparison.json').write_text(json.dumps(result,indent=2)+'\n');print(json.dumps(result,indent=2))
