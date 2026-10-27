from pathlib import Path
import base64,contextlib,hashlib,io,json,runpy,zipfile
root=Path(__file__).resolve().parents[2];snap=root/'instances/wmtech-1551/snapshots'
with contextlib.redirect_stdout(io.StringIO()):scope=runpy.run_path(str(root/'wmtech/tests/path-semantic.py'))
normalize=scope['normalized'];missing=object()
def leafdiff(a,b,path=''):
 if isinstance(a,dict) and isinstance(b,dict):return [v for k in set(a)|set(b) for v in leafdiff(a.get(k,missing),b.get(k,missing),path+'.'+k)]
 return [path.lstrip('.')] if a!=b else []
def keyorder(a,b,path=''):
 if isinstance(a,dict) and isinstance(b,dict):return ([path] if list(a)!=list(b) else [])+[v for k in set(a)&set(b) for v in keyorder(a[k],b[k],path+'.'+k)]
 return []
a=snap/'path-plain';b=snap/'path-staged'
def registry(folder):return {base64.b64decode(r['data_key']).decode():r['data_value'] for r in map(json.loads,normalize(folder,'xf_data_registry'))}
def options(folder):return {base64.b64decode(r['option_id']).decode():r['option_value'] for r in map(json.loads,normalize(folder,'xf_option'))}
registry_paths=leafdiff(registry(a),registry(b));option_ids=leafdiff(options(a),options(b))
assert registry_paths==['options.boardUrl'] and option_ids==['boardUrl'],(registry_paths,option_ids)
orders={label:keyorder(json.loads((snap/('path-'+label)/'registry-decoded.json').read_text())['values'],json.loads((b/'registry-decoded.json').read_text())['values']) for label in ['plain','prepared']}
assert not any(orders.values())
with zipfile.ZipFile(root/'archives/xenforo-2.3.13.zip') as z:
 archive={n[7:] for n in z.namelist() if n.startswith('upload/') and not n.endswith('/')};health=set(json.loads(z.read('upload/src/addons/XF/hashes.json')))
raw={p for p,v in json.loads((b/'files.json').read_text()).items() if v['targetArchiveFile']}
manifest=json.loads((snap/'path-inputs-manifest.json').read_text())
for key,digest in manifest.items():
 label,relative=key.split('/',1)
 assert hashlib.sha256((snap/('path-'+label)/relative).read_bytes()).hexdigest()==digest,'Private source corpus changed'
result={'plainRemainingNormalizedRegistryPaths':registry_paths,'plainRemainingNormalizedOptionIds':option_ids,'decodedRegistryKeyOrderDifferences':orders,'archiveFileSets':{'archive':len(archive),'health':len(health),'rawCompared':len(raw),'unhashedEntireArchive':len(archive-health),'unhashedAmongComparedFiles':len(raw-health),'archiveExcludedFromRawScan':sorted(archive-raw),'healthExcludedFromRawScan':sorted(health-raw)},'sourceSnapshotCorporaUntouched':True}
(root/'wmtech/reports/evidence/upgrade-path-comparison/comparison-residual-checks.json').write_text(json.dumps(result,indent=2)+'\n');print(json.dumps(result,indent=2))
