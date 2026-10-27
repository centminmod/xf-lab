from pathlib import Path
import collections, hashlib, json

root=Path(__file__).resolve().parents[3]
out=root/'wmtech/reports/evidence/1.5.50-claim-verification'
def read(name): return json.loads((out/name).read_text())

matrix=read('claim-matrix.json'); archive=read('archive-checks.json')
assert len(matrix['claims'])==86
assert matrix['counts']==dict(collections.Counter(c['verdict'] for c in matrix['claims']))
doc_ids={d['id'] for d in read('primary-documentation.json')}
for claim in matrix['claims']:
    assert set(claim['primaryDocumentation'])<=doc_ids, claim['id']
    for evidence in claim['evidence']:
        path=(root/'wmtech/reports/evidence'/evidence[7:]) if evidence.startswith('reused:') else out/evidence
        assert path.is_file(), (claim['id'], evidence)
for label,path in [('base','archives/xenforo-1.5.24.zip'),('wmtech','wmtech/xenforo_1.5.50_upgrade.zip')]:
    assert hashlib.sha256((root/path).read_bytes()).hexdigest()==archive['archiveSha256'][label]
runtime={n:read(n) for n in ['runtime-base-7.2.json','runtime-base-7.3.json','runtime-base-7.4.json','runtime-wmtech-7.3.json','runtime-wmtech-7.4.json']}
for name,data in runtime.items():
    assert len(data['probes'])==20 and all(p['completed'] for p in data['probes'].values()), name
    if 'wmtech' in name: assert all(not p['warningCounts'] for p in data['probes'].values()), name
stock=runtime['runtime-base-7.4.json']['probes'];patch=runtime['runtime-wmtech-7.4.json']['probes']
assert stock['native-session-object-control']['result']['objectHooksExecuted']==1
assert patch['native-session-object-control']['result']['objectHooksExecuted']==0
assert not patch['safe-helper']['result']['objectLikeStringAccepted']
assert stock['toggleClass-and-implode']['result']=='0 1 2' and patch['toggleClass-and-implode']['result']=='one three'
assert read('http-checks.json')['cookieCaseCount']==75
before=read('custom-xf_patch-seed.json');after=read('custom-xf_patch-upgrade.json')
for key in ['schemaSha256','tables','outdatedTemplates','outdatedPhrases','masterTemplates','masterPhrases']:
    assert before[key]==after[key],key
assert after['errorRows']==0 and after['customTemplatePreserved'] and after['translationPreserved']
browser=read('browser-controls.json')
assert all(browser['checks'].values()) and all(r['prototypePollutionReproduced'] for r in browser['runs'].values())
assert browser['hashFilters']['patched']=='#a-._Z'
assert len(read('lint-wmtech-7.4.json')['deprecatedFiles'])==5
assert not read('lint-wmtech-7.3.json')['syntaxErrors'] and not read('lint-wmtech-7.4.json')['syntaxErrors']
assert read('primary-downloads.json')['jquery124']['sri']==archive['jquery']['1.12.4Sri']
assert read('primary-downloads.json')['microsoftStockUrl']['status']==200
assert not read('requirements-50-php72.json')['requirementErrorKeys']
assert read('salt-continuity.json')['after']=={'oldRememberAccepted':False,'oldProxyAccepted':False,'emailStopKeyChanged':True,'storedVerificationAccepted':True}
for label,length in [('base',4),('wmtech',12)]:
    data=read('password-reset-'+label+'.json')
    assert data['controlledTrimmedPasswordLength']==length and data['newPasswordAuthenticates'] and data['originalAuthenticationBytesRestored']
for label,expected in [('base',True),('wmtech',False)]:
    assert read('transport-'+label+'-7.4-curl-control.json')['paypal']['untrustedCertificate']['connected'] is expected
assert not read('transport-wmtech-7.4-nocurl.json')['resolvePresent']
for label,patched in [('base',False),('wmtech',True)]:
    data=read('panel-followup-'+label+'.json')
    assert data['binding']['adminDisabled']['admin']['unrelatedV4'] is patched
    assert data['binding']['adminDisabled']['admin']['differentV6Subnet'] is patched
    assert data['binding']['publicWidened']['public']['differentV4Subnet'] is patched
    assert data['binding']['publicWidened']['public']['differentV6Subnet'] is patched
    assert set(data['writers'].values())=={'rejectedByExistingRegex'}
    assert data['legacyReadControls']['nativePreparedCustomFieldsIsArray'] is not patched
    assert bool(data['legacyReadControls']['nativeReportReadWarningCounts'].get('8')) is patched
    assert not data['configGeneration']['saltAutomaticallyInserted']
alphabet=read('password-alphabet-check.json')
assert alphabet['mappedMultiplicityCounts']=={'1':57,'2':2,'3':1} and alphabet['totalProbabilityNumerator']==64
assert read('panel-note.json')['status']=='verified'
summary={'status':'passed','claimChecks':len(matrix['claims']),'verdictCounts':matrix['counts'],'nativeProbeRuns':len(runtime),'nativeProbeCases':sum(len(d['probes']) for d in runtime.values()),'postPanelNativeSourceRuns':2,'postPanelSessionIpDecisions':60,'cookieCases':75,'customizedRenderedPages':len(read('http-checks.json')['renderedCustomizationPages']),'fullMinBundleControls':len(browser['runs']),'reusedEvidenceExplicitlyTagged':True,'originalArchivesUnchanged':True,'scope':'Evidence consistency and specific expected behavior assertions. Not a full product security certification.'}
(out/'verification-summary.json').write_text(json.dumps(summary,indent=2)+'\n')
print(json.dumps(summary,indent=2))
