from pathlib import Path
import subprocess,sys,json,re
lab=Path(__file__).resolve().parents[2] / 'instances/wmtech-1551';label=sys.argv[1];assert label in {'main','direct','final','plain','prepared'};direct=label!='main';forum='/review/'+label+'-webroot'
base=['docker','compose','-p','xf_wmtech_1551','-f',str(lab/'compose.yml'),'exec','-T']
args=base+(['--workdir',forum] if direct else [])+['php83','php','cmd.php','xf:upgrade','--skip-statistics']
r=subprocess.run(args,input='y\n',capture_output=True,text=True)
log=r.stdout+r.stderr;(lab/'snapshots'/('native-xf2-'+label+'.txt')).write_text(log)
assert r.returncode==0,log[-4000:]
assert 'Upgrade completed successfully.' in log
versions=list(dict.fromkeys(re.findall(r'Running upgrade to ([^,\n\r]+), step',log)))
result={'path':label,'exitCode':r.returncode,'upgradeCompleted':True,'nativeVersionCount':len(versions),'firstVersion':versions[0],'lastVersion':versions[-1],'target':'2.3.13','statisticsOptInSkipped':True}
(lab/'evidence'/('native-xf2-'+label+'.json')).write_text(json.dumps(result,indent=2)+'\n');print(json.dumps(result,indent=2))
