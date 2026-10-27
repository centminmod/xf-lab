from pathlib import Path
import http.cookies
import json
import re
import urllib.error
import urllib.parse
import urllib.request

lab = Path(__file__).resolve().parents[2] / 'instances/wmtech-1551'
endpoint = lab / 'webroot/__xf151_cookie.php'
endpoint.write_text("<?php require '/wmtech/tests/cookie-endpoint.php';\n")
result = {}
try:
    cases = [({'legacy':'Strict'}, 'Strict', False), ({'legacy':'Lax','lower':'Strict'}, 'Strict', False),
             ({'lower':''}, '', False), ({'lower':'None','secure':'1'}, 'None', True),
             ({'legacy':'Strict','lower':'Lax','secure':'0'}, 'Lax', False)]
    for index, (params, expected, secure) in enumerate(cases):
        response = urllib.request.urlopen('http://localhost:8774/__xf151_cookie.php?' + urllib.parse.urlencode(params))
        cookie = http.cookies.SimpleCookie()
        cookie.load(response.headers['Set-Cookie'])
        item = cookie['xf_review']
        assert item['samesite'] == expected and bool(item['secure']) == secure and item['httponly']
        result[f'cookie-policy-{index}'] = True
finally:
    endpoint.unlink()
for action in ['facebook','facebook-register','google','google-register']:
    try:
        response = urllib.request.urlopen('http://localhost:8774/index.php?register/'+action)
    except urllib.error.HTTPError as error:
        response = error
    assert response.status == 404, (action, response.status)
    result['retired-endpoint-'+action] = True
body = urllib.request.urlopen('http://localhost:8774/index.php?login/').read().decode()
assert not re.search(r'(?:\?|/)register/(?:facebook|google)',body)
assert all(value not in body for value in ['fixture-facebook-id','fixture-facebook-secret','fixture-google-id','fixture-google-secret'])
result['retired-login-buttons-hidden'] = True
result['provider-values-not-exposed'] = True
(lab / 'evidence/http-policy-1.5.51.json').write_text(json.dumps(result,indent=2)+'\n')
print(json.dumps(result,indent=2))
