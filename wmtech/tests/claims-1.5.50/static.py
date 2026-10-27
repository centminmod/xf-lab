from pathlib import Path
import base64, difflib, hashlib, json, re, zipfile, xml.etree.ElementTree as ET

root = Path(__file__).resolve().parents[3]
out = root / 'wmtech/reports/evidence/1.5.50-claim-verification'
out.mkdir(parents=True, exist_ok=True)
archives = {'base': root/'archives/xenforo-1.5.24.zip', 'wmtech': root/'wmtech/xenforo_1.5.50_upgrade.zip'}
files = {}
for label, path in archives.items():
    with zipfile.ZipFile(path) as archive:
        files[label] = {n: archive.read(n) for n in archive.namelist() if not n.endswith('/')}
base, patch = files['base'], files['wmtech']
result = {'archiveSha256': {label: hashlib.sha256(path.read_bytes()).hexdigest() for label,path in archives.items()},
          'changed': sorted(n for n in base.keys() & patch.keys() if base[n] != patch[n]),
          'added': sorted(patch.keys()-base.keys()), 'omitted': sorted(base.keys()-patch.keys())}
assert len(result['changed']) == 45 and len(result['added']) == 3 and len(result['omitted']) == 53
changelog = patch['CHANGELOG-1.5.50.md']
(out/'original-changelog.txt').write_bytes(changelog)
result['changelogSha256'] = hashlib.sha256(changelog).hexdigest()
result['xml'] = {}
for name in ['templates','phrases','admin_templates','email_templates']:
    key='upload/install/data/'+name+'.xml'
    result['xml'][name] = {'byteIdentical':base[key] == patch[key], 'sha256':hashlib.sha256(patch[key]).hexdigest()}
    assert base[key] == patch[key]
templates=ET.fromstring(patch['upload/install/data/templates.xml'])
phrases=ET.fromstring(patch['upload/install/data/phrases.xml'])
text=''.join(t.text or '' for t in templates)
result['templateCount']=len(templates);result['phraseCount']=len(phrases)
result['publicTemplateHookOccurrences']=len(re.findall(r'<xen:hook\b',text))
result['publicTemplateClosingHookTags']=len(re.findall(r'</xen:hook\s*>',text))
result['publicTemplateSelfClosingHooks']=len(re.findall(r'<xen:hook\b[^>]*?/>',text))
result['hookOpeningTagsByXml']={n:len(re.findall(rb'<xen:hook\b',b)) for n,b in patch.items() if n.endswith('.xml') and b'<xen:hook' in b}
result['hookOpeningTagsAllXml']=sum(result['hookOpeningTagsByXml'].values())
result['flashContracts']={needle:needle in text for needle in ['SWFUploadPlaceHolder','swfupload','enable_flash_uploader']}
result['markedFiles']=sorted(n for n,b in patch.items() if n.startswith('upload/') and b'XF1550-PATCH:' in b)
result['markedFileCount']=len(result['markedFiles'])
result['buildScriptsPresent']=[n for n in patch if n.startswith('build/')]
result['jquery']={'1.11.0Retained':patch['upload/js/jquery/jquery-1.11.0.min.js']==base['upload/js/jquery/jquery-1.11.0.min.js'],
                  '1.12.4Sri':'sha256-'+base64.b64encode(hashlib.sha256(patch['upload/js/jquery/jquery-1.12.4.min.js']).digest()).decode()}
result['safeHelperByteIdentical']=base['upload/library/XenForo/Helper/Php.php']==patch['upload/library/XenForo/Helper/Php.php']
result['safeHelperHasNativeAllowedClassesFlag']=b'allowed_classes' in patch['upload/library/XenForo/Helper/Php.php']
result['azureOmittedFiles']=len([n for n in result['omitted'] if '/WindowsAzure/' in n])
result['libxmlNoentPaths']=[n for n,b in patch.items() if n.startswith('upload/') and n.endswith('.php') and b'LIBXML_NOENT' in b]
result['qrHelperPaths']=[n for n,b in patch.items() if n.startswith('upload/') and n.endswith(('.php','.xml','.js')) and b'getQRCodeGoogleUrl(' in b]
result['rawCoreCookiePaths']=[n for n,b in patch.items() if n.startswith('upload/library/XenForo/') and re.search(rb'\bset(?:raw)?cookie\s*\(',b)]
result['upgradeStepCode']=patch['upload/library/XenForo/Install/Upgrade/1055070-1550.php'].decode()
result['optionsXmlChanged'] = base['upload/install/data/options.xml'] != patch['upload/install/data/options.xml']
result['globalSaltDefaultChanged']=re.search(rb"'globalSalt'\s*=>\s*'([^']+)'",base['upload/library/XenForo/Application.php']).group(1)!=re.search(rb"'globalSalt'\s*=>\s*'([^']+)'",patch['upload/library/XenForo/Application.php']).group(1)
result['minDirectoryPresent']={label:any(n.startswith('upload/js/xenforo/min/') for n in data) for label,data in files.items()}
result['minifiedSentinelPresent']=b'_XF_JS_UNCOMPRESSED_TEST_' in patch['upload/js/xenforo/xenforo.js']
core=patch['upload/js/xenforo/full/xenforo.js'].decode()
result['corePhraseLiteralKeys']=sorted(set(re.findall(r'XenForo\.phrases\.([A-Za-z_$][\w$]*)',core)+re.findall(r'''XenForo\.phrases\[['"]([^'"]+)['"]\]''',core)))
result['corePhpToggleClassCallers']=[n for n,b in patch.items() if n.startswith('upload/library/XenForo/') and n.endswith('.php') and n!='upload/library/XenForo/Template/Helper/Core.php' and b'toggleClass(' in b]
result['jqueryBrowserCallSitesFullSource']=sum(len(re.findall(rb'\$\.browser\b',b)) for n,b in patch.items() if n.startswith('upload/js/xenforo/full/') and n.endswith('.js'))
members={label:set(re.findall(rb"^\s*'([^']+)'\s*=>\s*'[a-f0-9]{32}'",data['upload/library/XenForo/Install/Data/FileSums.php'],re.M)) for label,data in files.items()}
result['manifestMembership']={'baseCount':len(members['base']),'patchCount':len(members['wmtech']),'added':sorted(x.decode() for x in members['wmtech']-members['base']),'removed':sorted(x.decode() for x in members['base']-members['wmtech'])}
assert result['manifestMembership']=={'baseCount':1238,'patchCount':1238,'added':['js/jquery/jquery-1.12.4.min.js','library/XenForo/Install/Upgrade/1055070-1550.php'],'removed':['fb_channel.php','js/swfupload/swfupload.min.js']}
assert result['hookOpeningTagsAllXml']==169 and result['markedFileCount']==44
assert result['safeHelperByteIdentical'] and result['safeHelperHasNativeAllowedClassesFlag']
assert result['globalSaltDefaultChanged'] and not result['minifiedSentinelPresent']

# Export only archive code and bounded public upstream context, never lab config or values.
def sanitized(code, label='TARGET'):
    return re.sub(r"(?m)^([^\n]*'globalSalt'\s*=>\s*)'[^']+'([^\n]*)$",lambda m:m[1]+"'["+label+" ARCHIVE DEFAULT REDACTED]'"+m[2],code.replace('\r\n','\n'))

comparison=['Original archive comparison. Base and target SHA-256 are in archive-checks.json.',
            'Line endings are normalized for this evidence export only. Original archive bytes are unchanged.',
            'Diff hunk numbers refer to archive sources. Cite this export by its own current line numbers.',
            'Factory salt literals are redacted; archive comparison independently records that the default changes.',
            'Excluded generated diffs: FileSums.php, minified xenforo.js and filter_list.js. INSTALL-UPGRADE.txt is inventoried separately.',
            'All other changed native PHP, readable JavaScript and options XML follow. Unchanged contracts follow the diffs.', '']
diff_lines=0
for n in result['changed']:
    if not n.startswith('upload/') or n.endswith('/FileSums.php') or n in ['upload/js/xenforo/xenforo.js','upload/js/xenforo/filter_list.js']:
        continue
    old=sanitized(base[n].decode(),'BASE');new=sanitized(patch[n].decode())
    diff=list(difflib.unified_diff(old.splitlines(True),new.splitlines(True),fromfile='stock24/'+n,tofile='original50/'+n,n=4))
    comparison.append(''.join(diff));diff_lines+=len(diff)
n='upload/library/XenForo/Install/Upgrade/1055070-1550.php'
comparison.extend(['=== ADDED original50/'+n+' ===',patch[n].decode()])

contracts={
 'upload/library/XenForo/Helper/Php.php':[(194,214),(245,272)],
 'upload/library/XenForo/Application.php':[(1393,1481),(1498,1506),(1662,1687)],
 'upload/library/XenForo/Controller.php':[(1113,1165)],
 'upload/library/XenForo/Model.php':[(302,335)],
 'upload/library/XenForo/Upload.php':[(165,246),(270,305)],
 'upload/library/XenForo/ViewPublic/Member/Find.php':[(1,30)],
 'upload/library/XenForo/ViewPublic/Attachment/View.php':[(25,45)],
 'upload/library/XenForo/Helper/Ip.php':[(1,40)],
 'upload/library/Zend/Xml/Security.php':[(1,110)],
 'upload/library/XenForo/Install/Model/Install.php':[(1,34)],
 'upload/library/XenForo/Model/UserConfirmation.php':[(59,85),(96,117),(265,302)],
 'upload/library/XenForo/Model/User.php':[(1352,1374),(1432,1462)],
 'upload/library/XenForo/ControllerPublic/Register.php':[(1032,1070)],
 'upload/js/xenforo/full/xenforo.js':[(1238,1298),(2775,2788),(9014,9040)]
}
for n,ranges in contracts.items():
    for start,end in ranges:
        comparison.extend(['=== original50/'+n+':'+str(start)+'-'+str(end)+'; unchanged contract unless diff above ===',
                           ''.join(sanitized(patch[n].decode()).splitlines(True)[start-1:end])])
for label in ['base','wmtech']:
    for n,start,end in [('upload/library/XenForo/Model/Deferred.php',237,303),('upload/library/XenForo/Session.php',790 if label=='wmtech' else 782,834 if label=='wmtech' else 825)]:
        comparison.extend(['=== '+label+'/'+n+':'+str(start)+'-'+str(end)+'; caller context ===',
                           ''.join(sanitized(files[label][n].decode(),label.upper()).splitlines(True)[start-1:end])])
upstream=root/'instances/wmtech-claims/private/upstream-zend-xml.php'
if upstream.exists():
    lines=upstream.read_text().splitlines(True)
    for start,end in [(66,123),(156,168)]:
        comparison.extend(['=== Public upstream Zend Framework release-1.12.20/library/Zend/Xml/Security.php:'+str(start)+'-'+str(end)+'; https://raw.githubusercontent.com/zendframework/zf1/release-1.12.20/library/Zend/Xml/Security.php ===',''.join(lines[start-1:end])])
result['sourceComparisonDiffLines']=diff_lines
(out/'source-comparison.txt').write_text('\n'.join(comparison)+'\n')
(out/'archive-checks.json').write_text(json.dumps(result,indent=2)+'\n')
print(json.dumps({k:result[k] for k in ['templateCount','phraseCount','publicTemplateHookOccurrences','markedFileCount','buildScriptsPresent','jquery','safeHelperByteIdentical','safeHelperHasNativeAllowedClassesFlag','azureOmittedFiles','libxmlNoentPaths','qrHelperPaths']},indent=2))
