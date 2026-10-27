from pathlib import Path
import base64,json,sys
root=Path(__file__).resolve().parents[2];lab=root/'instances/wmtech-1551'
left,right=sys.argv[1:3];assert left in {'plain','prepared'} and right=='staged'
a=lab/'snapshots'/('path-'+left);b=lab/'snapshots'/('path-'+right)
def load(folder,name):return json.loads((folder/(name+'.json')).read_text())
def text(value):return None if value is None else base64.b64decode(value).decode(errors='replace')
def changes(name,meta):
 ar=load(a,name);br=load(b,name);primary=meta['primary']
 if not primary:return {'leftRows':len(ar),'rightRows':len(br),'stablePrimaryKey':False,'changedColumns':[]}
 def keyed(rows):return {tuple(row[k] for k in primary):row for row in rows}
 ak,bk=keyed(ar),keyed(br);assert len(ak)==len(ar) and len(bk)==len(br),'Duplicate primary key in snapshot';common=set(ak)&set(bk);different=[k for k in common if ak[k]!=bk[k]]
 columns=sorted({c for k in different for c in ak[k] if ak[k][c]!=bk[k][c]})
 result={'leftRows':len(ar),'rightRows':len(br),'leftOnlyRows':len(set(ak)-set(bk)),'rightOnlyRows':len(set(bk)-set(ak)),'changedRows':len(different),'changedColumns':columns}
 if name=='xf_option':result['changedOptionIds']=sorted(text(ak[k]['option_id']) for k in different);result['leftOnlyOptionIds']=sorted(text(ak[k]['option_id']) for k in set(ak)-set(bk));result['rightOnlyOptionIds']=sorted(text(bk[k]['option_id']) for k in set(bk)-set(ak))
 if name=='xf_upgrade_log':result['leftOnlyVersions']=sorted(int(text(ak[k]['version_id'])) for k in set(ak)-set(bk));result['rightOnlyVersions']=sorted(int(text(bk[k]['version_id'])) for k in set(bk)-set(ak))
 return result
ai,bi=load(a,'index'),load(b,'index');schemasA,schemasB=load(a,'schema'),load(b,'schema')
common=set(ai)&set(bi);different={name:changes(name,ai[name]) for name in sorted(common) if ai[name]['sha256']!=bi[name]['sha256']}
def flat(value,prefix=''):
 if isinstance(value,dict) and value:return {k:v for key,item in value.items() for k,v in flat(item,prefix+('.' if prefix else '')+key).items()}
 return {prefix:value}
ca,cb=flat(load(a,'config')),flat(load(b,'config'));missing=object();configDifferent=sorted(k for k in set(ca)|set(cb) if ca.get(k,missing)!=cb.get(k,missing) and k!='db.dbname')
fa,fb=load(a,'files'),load(b,'files');targetA={p:v for p,v in fa.items() if v['targetArchiveFile']};targetB={p:v for p,v in fb.items() if v['targetArchiveFile']}
legacyA={p:v['sha256'] for p,v in fa.items() if not v['targetArchiveFile']};legacyB={p:v['sha256'] for p,v in fb.items() if not v['targetArchiveFile']}
result={'comparison':left+'-to-staged','tableCountLeft':len(ai),'tableCountRight':len(bi),'leftOnlyTables':sorted(set(ai)-set(bi)),'rightOnlyTables':sorted(set(bi)-set(ai)),'schemaDifferences':sorted(k for k in set(schemasA)|set(schemasB) if schemasA.get(k)!=schemasB.get(k)),'identicalRawTables':len(common)-len(different),'differentRawTables':different,'loadedConfigDifferentKeys':configDifferent,'ignoredConfigKeys':['db.dbname'],'effectivePermissionsEqual':load(a,'permissions')==load(b,'permissions'),'targetArchiveFilesCompared':len(targetA),'targetArchiveFilesEqual':targetA==targetB,'filesOutsideTargetArchive':{'leftCount':len(legacyA),'rightCount':len(legacyB),'leftOnly':sorted(set(legacyA)-set(legacyB)),'rightOnly':sorted(set(legacyB)-set(legacyA)),'changed':sorted(p for p in set(legacyA)&set(legacyB) if legacyA[p]!=legacyB[p])},'privateSnapshotValuesExported':False,'normalization':'Only DDL AUTO_INCREMENT counters and isolated config dbname are excluded at this inventory stage. Every other raw difference is reported for classification.'}
(lab/'evidence'/('path-comparison-'+left+'.json')).write_text(json.dumps(result,indent=2)+'\n');print(json.dumps(result,indent=2))
