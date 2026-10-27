from pathlib import Path
from email.utils import parsedate_to_datetime
import datetime, json, os, urllib.parse, urllib.request, urllib.error

root=Path(__file__).resolve().parents[3];lab=root/'instances/wmtech-claims';rows=[]
def write_config(config, data):
    temporary=config.with_name('config-claims-temporary.php')
    with temporary.open('wb') as stream:stream.write(data);stream.flush();os.fsync(stream.fileno())
    temporary.replace(config)
for source in ['base','wmtech']:
    original=(lab/(source+'-webroot/library/config.php')).read_bytes()
    assert b'?>' not in original, 'Expected a PHP-only fixture config'
    for case,secure in [('default',None),('on',True),('off',False)]:
        directory=lab/'cookie-configs'/(source+'-'+case);directory.mkdir(parents=True,exist_ok=True)
        data=original if secure is None else original+('\n$config[\'cookie\'][\'secure\']='+('true' if secure else 'false')+';\n').encode()
        write_config(directory/'config.php',data)
for label,ports in [('base',[8872,8873]),('wmtech',[8872,8873,8874])]:
    config=lab/(label+'-webroot/library/config.php');original=config.read_bytes()
    try:
        for secure in [None,True,False]:
            for port in ports:
                for policy,delete in [(None,False),('Strict',False),('None',False),('',False),('Lax',True)]:
                    query={'source':label,'secureConfig':'default' if secure is None else ('on' if secure else 'off')}
                    if policy is not None:query['policy']=policy
                    if delete:query['delete']='1'
                    url='http://localhost:'+str(port)+'/claims-cookie.php?'+urllib.parse.urlencode(query)
                    try: response=urllib.request.urlopen(url,timeout=15)
                    except urllib.error.HTTPError as error:
                        body=error.read().decode(errors='replace');(lab/'private/cookie-fixture-error.html').write_text(body)
                        raise RuntimeError(str((label,port,secure,policy,delete))+' '+body[-1800:]) from error
                    headers=response.headers.get_all('Set-Cookie') or []
                    cookie=next((h for h in headers if h.startswith('xf_claims=')),None);assert cookie is not None,(label,port,policy)
                    attrs={p.strip().split('=',1)[0].lower():(p.strip().split('=',1)[1] if '=' in p else True) for p in cookie.split(';')[1:]}
                    expected=('Lax' if policy is None else policy) if label=='wmtech' and port!=8872 else None
                    assert attrs.get('samesite')==(expected or None),(label,policy,attrs)
                    assert ('secure' in attrs)==(label=='wmtech' and secure is True),(label,secure,attrs)
                    assert 'httponly' in attrs
                    rows.append({'source':label,'port':port,'policy':policy,'delete':delete,'configuredSecure':secure,'sameSiteHeader':attrs.get('samesite'),'secureHeader':'secure' in attrs,'httpOnlyHeader':True})
    finally:assert config.read_bytes()==original
pages=[]
for label,ports in [('base',[8872,8873]),('wmtech',[8873,8874])]:
    if label=='base':ports=[8872]
    for port in ports:
        response=urllib.request.urlopen('http://localhost:'+str(port)+'/',timeout=15);text=response.read().decode()
        values={'source':label,'port':port,'status':response.status,'childTemplateRendered':'claims-child-style' in text,'addonModificationRendered':'claims-addon-marker' in text,'translatedPhraseRendered':'Claim translated forums' in text}
        assert all(values[k] for k in ['childTemplateRendered','addonModificationRendered','translatedPhraseRendered']),values
        pages.append(values)
rgba=[]
for label,port in [('base',8872),('wmtech',8874)]:
    r=urllib.request.urlopen('http://localhost:'+str(port)+'/rgba.php?r=1&g=2&b=3&a=200',timeout=15);data=r.read();expires=parsedate_to_datetime(r.headers['Expires']);future=expires>datetime.datetime.now(datetime.timezone.utc)
    assert data.startswith(b'\x89PNG\r\n\x1a\n') and future==(label=='wmtech')
    rgba.append({'source':label,'pngSignature':True,'expiresInFuture':future,'expiresHeader':r.headers['Expires']})
result={'cookieCases':rows,'cookieCaseCount':len(rows),'renderedCustomizationPages':pages,'rgba':rgba,'scope':'Loopback HTTP headers and native rendered pages. Secure override is tested with HTTPS server detection false, as at a TLS-offloading proxy. No cross-site browser-cookie or production proxy certification.'}
(root/'wmtech/reports/evidence/1.5.50-claim-verification/http-checks.json').write_text(json.dumps(result,indent=2)+'\n')
print('PASS',len(rows),'cookie cases,',len(pages),'customized rendered pages and two rgba headers')
