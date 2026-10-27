from pathlib import Path
import urllib.request,urllib.error,urllib.parse,http.cookiejar,http.cookies,ssl,html,re,json,hashlib
lab=Path(__file__).resolve().parents[2] / 'instances/wmtech-1551'
base='https://localhost:8743/';context=ssl.create_default_context(cafile=str(lab/'evidence/tls-lab-cert.pem'))
rows=[]
def client():
 jar=http.cookiejar.CookieJar();return jar,urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar),urllib.request.HTTPSHandler(context=context))
def fetch(opener,path,data=None):
 try:r=opener.open(urllib.request.Request(base+path,data=urllib.parse.urlencode(data).encode() if data is not None else None),timeout=30)
 except urllib.error.HTTPError as e:r=e
 raw=r.read();body=raw.decode(errors='replace')
 cookies=[]
 for value in r.headers.get_all('Set-Cookie') or []:
  c=http.cookies.SimpleCookie();c.load(value)
  for name,m in c.items():cookies.append({'name':name,'secure':bool(m['secure']),'httpOnly':bool(m['httponly']),'samesite':m['samesite']})
 rows.append({'path':path,'status':r.status,'cookies':cookies})
 return r,body,raw
def token(body):
 m=re.search(r'name="_xfToken" value="([^"]*)"',body);assert m,'CSRF form token missing';return html.unescape(m.group(1))
for username,password in [('admin','Admin123Pass'),('legacy_user','LegacyUserPass123')]:
 jar,opener=client();r,body,_=fetch(opener,'index.php?login/');assert r.status==200
 r,body,_=fetch(opener,'index.php?login/login',{'login':username,'password':password,'remember':'1','_xfToken':token(body),'_xfRedirect':base})
 assert r.status==200 and any(c.name=='xf_user' for c in jar),username+' login failed'
 r,body,_=fetch(opener,'index.php?account/');assert r.status==200 and 'data-logged-in="true"' in body,username+' account failed'
 for cookie in jar:
  if cookie.name.startswith('xf_'):assert cookie.secure,cookie.name+' missing Secure on HTTPS'
 assert any(c.name=='xf_user' and c.get_nonstandard_attr('SameSite')=='Lax' for c in jar)
 if username=='admin':
  r,body,_=fetch(opener,'index.php?threads/1/');assert r.status==200 and 'Migration fixture' in body and 'café' in body
  r,body,raw=fetch(opener,'index.php?attachments/1/');assert r.status==200 and hashlib.sha256(raw).hexdigest()=='0b62ac71b94f6474796ee682094e895e9b0b209324aa228c1fd40fa0c6e306da'
result={'transport':'loopback Apache TLS, trusted self-signed test certificate','certificateVerificationEnabled':True,'accountLogins':2,'bothPasswordSchemesPassed':True,'attachmentDownloadSha256Matched':True,'cookieValuesRetained':False,'requests':rows,'productionProxyTested':False}
(lab/'evidence/http-2.3.13.json').write_text(json.dumps(result,indent=2)+'\n');print(json.dumps(result,indent=2))
