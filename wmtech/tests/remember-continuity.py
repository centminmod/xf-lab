from pathlib import Path
import json
import re
import sys
import urllib.error
import urllib.parse
import urllib.request

stage = sys.argv[1]
lab = Path(__file__).resolve().parents[2] / "instances/wmtech-1551"
results = {}
for old_stage in sys.argv[2:]:
    saved = json.loads((lab / f"snapshots/state-{old_stage}.json").read_text())
    cookie = urllib.parse.quote("1," + saved["signatures"]["remember"])
    request = urllib.request.Request("http://localhost:8774/index.php?account/", headers={"Cookie": "xf_user=" + cookie})
    try:
        response = urllib.request.urlopen(request, timeout=15)
    except urllib.error.HTTPError as error:
        response = error
    body = response.read().decode()
    classes = re.search(r'<html[^>]+class="([^"]+)"', body)
    assert classes, "Missing forum response"
    results[old_stage] = "LoggedIn" in classes.group(1).split()
(lab / f"evidence/remember-{stage}.json").write_text(json.dumps(results, indent=2) + "\n")
print(json.dumps(results, indent=2))
