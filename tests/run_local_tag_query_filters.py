"""Run candidate PHP sources in the local Joomla container without installation."""
from pathlib import Path
import base64
import json
import subprocess
import sys

root = Path(__file__).resolve().parents[1]
base = root / 'package/plugins/system/tagrouting/src'
sources = {'SuperSoft\\Plugin\\System\\TagRouting\\' + '\\'.join(p.relative_to(base).with_suffix('').parts): base64.b64encode(p.read_bytes()).decode() for p in base.rglob('*.php')}
script = (root / (sys.argv[1] if len(sys.argv)>1 else 'tests/local_tag_query_filters.php')).read_text(encoding='utf-8')
key = 'SuperSoft\\Plugin\\System\\TagRouting\\Extension\\TagRouting'
code = base64.b64decode(sources[key]).replace(b"dirname(__DIR__, 2) . '/forms/routing.xml'", b"$GLOBALS['smarttag_form_fixture']")
sources[key] = base64.b64encode(code).decode()
forms = [base64.b64encode((root / p).read_bytes()).decode() for p in ['package/plugins/system/tagrouting/forms/routing.xml','package/components/com_tagrouting/site/tmpl/tags/default.xml','package/plugins/system/tagrouting/tagrouting.xml','package/plugins/system/tagrouting/language/en-GB/plg_system_tagrouting.ini']]
script = script.replace('// Run via', '$formSources = ' + repr(forms) + ';\n// Run via', 1)
script = script.replace('// Run via', '$sources = json_decode(base64_decode(\'' + base64.b64encode(json.dumps(sources).encode()).decode() + '\'), true);\n// Run via', 1)
result = subprocess.run(['docker','exec','-i','catichisi-local-web-1','php'],input=script,text=True,capture_output=True,encoding='utf-8',timeout=60)
print(result.stdout)
if result.stderr:
    print(result.stderr)
raise SystemExit(result.returncode)
