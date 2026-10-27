from pathlib import Path
import hashlib,html,http.cookiejar,json,re,ssl,subprocess,urllib.error,urllib.parse,urllib.request
root=Path(__file__).resolve().parents[2];lab=root/'instances/wmtech-1551'
b=['docker','compose','-p','xf_wmtech_1551','-f',str(lab/'compose.yml'),'exec','-T','php83']
configuration='''<VirtualHost *:443>
ServerName localhost
DocumentRoot /var/www/html
SSLEngine on
SSLCertificateFile /tmp/xf-lab-tls.crt
SSLCertificateKeyFile /tmp/xf-lab-tls.key
<Directory /var/www/html>
Require all granted
AllowOverride All
</Directory>
'''
for label,folder in [('plain','plain'),('prepared','prepared'),('staged','final')]:
 configuration+='Alias /parity-'+label+'/ /review/'+folder+'-webroot/\n<Directory /review/'+folder+'-webroot>\nRequire all granted\nAllowOverride All\n</Directory>\n'
configuration+='</VirtualHost>\n'
subprocess.run(b+['tee','/etc/apache2/sites-available/xf-lab-ssl.conf'],input=configuration,text=True,capture_output=True,check=True)
subprocess.run(b+['apache2ctl','-k','graceful'],capture_output=True,check=True)
context=ssl.create_default_context(cafile=str(lab/'evidence/tls-lab-cert.pem'));results={}
def token(body):
 m=re.search(r'name="_xfToken" value="([^"]*)"',body);assert m;return html.unescape(m.group(1))
for label in ['plain','prepared','staged']:
 base='https://localhost:8743/parity-'+label+'/'
 states=[]
 for username,password in [('admin','Admin123Pass'),('legacy_user','LegacyUserPass123')]:
  jar=http.cookiejar.CookieJar();opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar),urllib.request.HTTPSHandler(context=context))
  def fetch(path,data=None):
   try:r=opener.open(urllib.request.Request(base+path,data=urllib.parse.urlencode(data).encode() if data is not None else None),timeout=30)
   except urllib.error.HTTPError as e:r=e
   raw=r.read();return r,raw.decode(errors='replace'),raw
  r,body,_=fetch('index.php?login/');assert r.status==200
  r,body,_=fetch('index.php?login/login',{'login':username,'password':password,'remember':'1','_xfToken':token(body),'_xfRedirect':base})
  assert r.status==200 and any(c.name=='xf_user' for c in jar)
  r,body,_=fetch('index.php?account/');assert r.status==200 and 'data-logged-in="true"' in body
  r,body,_=fetch('index.php?threads/1/');assert r.status==200 and 'Migration fixture' in body
  r,body,raw=fetch('index.php?attachments/1/');assert r.status==200 and hashlib.sha256(raw).hexdigest()=='0b62ac71b94f6474796ee682094e895e9b0b209324aa228c1fd40fa0c6e306da'
  policies=[{'name':c.name,'secure':c.secure,'sameSite':c.get_nonstandard_attr('SameSite'),'httpOnly':c.has_nonstandard_attr('HttpOnly')} for c in jar if c.name.startswith('xf_')]
  assert all(c['secure'] for c in policies)
  assert all(c['sameSite']==(None if label=='plain' else 'Lax') for c in policies)
  states.append({'user':username,'loginAccountThreadAndAttachmentPassed':True,'cookiePolicies':policies})
 results[label]={'certificateVerificationEnabled':True,'loopbackTls':True,'accountLogins':len(states),'accounts':states,'cookieValuesExported':False,'productionProxyTested':False}
(lab/'evidence/path-http.json').write_text(json.dumps(results,indent=2)+'\n');print(json.dumps(results,indent=2))
